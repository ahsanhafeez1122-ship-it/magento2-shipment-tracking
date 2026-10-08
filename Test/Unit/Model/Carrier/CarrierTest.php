<?php
declare(strict_types=1);

namespace AhsanHafeez\ShipmentTracking\Test\Unit\Model\Carrier;

use AhsanHafeez\ShipmentTracking\Model\Carrier\CarrierParser;
use AhsanHafeez\ShipmentTracking\Model\Carrier\CarrierResolver;
use PHPUnit\Framework\TestCase;

final class CarrierTest extends TestCase
{
    private const CONFIG = <<<TXT
dpd | DPD | https://track.dpd.co.uk/parcels/{number} | ^\d{14}$ | dpd uk
dpdlocal | DPD Local | https://track.dpdlocal.co.uk/parcels/{number}?postcode={postcode} | ^\d{14}$ | dpd local
royalmail | Royal Mail | https://www.royalmail.com/track-your-item#/tracking-results/{number} | ^[A-Z]{2}\d{9}GB$ | royal mail, rm tracked
evri | Evri | https://www.evri.com/track/parcel/{number} | | hermes
TXT;

    public function testParsesConfigLines(): void
    {
        $result = (new CarrierParser())->parse(self::CONFIG);

        self::assertSame([], $result['errors']);
        self::assertSame(['dpd', 'dpdlocal', 'royalmail', 'evri'], array_keys($result['carriers']));
        self::assertContains('hermes', $result['carriers']['evri']->aliases);
        self::assertContains('evri', $result['carriers']['evri']->aliases);
    }

    public function testReportsBadLinesAndKeepsGoodOnes(): void
    {
        $result = (new CarrierParser())->parse(implode("\n", [
            '# comment lines are skipped',
            'ok | OK Couriers | https://ok.example/{number}',
            'x | Too Short Code | https://x.example/{number}',
            'nolink | No Link | https://example.com/track',
            'insecure | Insecure | http://example.com/{number}',
            'badregex | Bad Regex | https://example.com/{number} | ([a-z',
            'ok | Duplicate | https://ok2.example/{number}',
        ]));

        self::assertSame(['ok'], array_keys($result['carriers']));
        self::assertCount(5, $result['errors']);
        self::assertStringStartsWith('Line 3:', $result['errors'][0]);
    }

    public function testBuildsUrlWithCleanNumberAndPostcode(): void
    {
        $carriers = (new CarrierParser())->parse(self::CONFIG)['carriers'];

        self::assertSame(
            'https://track.dpdlocal.co.uk/parcels/15501234567890?postcode=SW1A1AA',
            $carriers['dpdlocal']->buildUrl(' 1550 1234 5678 90 ', 'sw1a 1aa')
        );
        self::assertSame(
            'https://www.royalmail.com/track-your-item#/tracking-results/AB123456789GB',
            $carriers['royalmail']->buildUrl('ab123456789gb')
        );
    }

    public function testUrlEncodesUnexpectedCharacters(): void
    {
        $carriers = (new CarrierParser())->parse(self::CONFIG)['carriers'];

        self::assertSame('https://www.evri.com/track/parcel/A%2FB%3F', $carriers['evri']->buildUrl('a/b?'));
    }

    public function testNumberFormatCheck(): void
    {
        $carriers = (new CarrierParser())->parse(self::CONFIG)['carriers'];

        self::assertTrue($carriers['royalmail']->looksValid('AB 123 456 789 GB'));
        self::assertFalse($carriers['royalmail']->looksValid('AB12345678GB'));
        self::assertFalse($carriers['dpd']->looksValid('1550123456789'));
        self::assertTrue($carriers['evri']->looksValid('anything'), 'no pattern means no warning');
    }

    /**
     * @dataProvider resolveCases
     */
    public function testResolvesCarrierFromCodeOrTitle(string $code, string $title, ?string $expected): void
    {
        $carriers = (new CarrierParser())->parse(self::CONFIG)['carriers'];

        $carrier = (new CarrierResolver())->resolve($carriers, $code, $title);

        self::assertSame($expected, $carrier?->code);
    }

    /**
     * @return array<string, array{string, string, ?string}>
     */
    public static function resolveCases(): array
    {
        return [
            'exact code' => ['dpd', 'Anything', 'dpd'],
            'custom with alias title' => ['custom', 'Royal Mail', 'royalmail'],
            'custom, title with punctuation' => ['custom', 'Royal-Mail!', 'royalmail'],
            'old brand name' => ['custom', 'Hermes', 'evri'],
            'longest alias wins' => ['custom', 'DPD Local Next Day', 'dpdlocal'],
            'title contains carrier' => ['custom', 'DPD Next Day 12:00', 'dpd'],
            'unknown carrier' => ['custom', 'Bob\'s Van', null],
            'empty title' => ['custom', '', null],
            'unknown code falls back to title' => ['tablerate', 'Royal Mail Tracked 24', 'royalmail'],
        ];
    }
}
