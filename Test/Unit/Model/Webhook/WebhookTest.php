<?php
declare(strict_types=1);

namespace AhsanHafeez\ShipmentTracking\Test\Unit\Model\Webhook;

use AhsanHafeez\ShipmentTracking\Model\Status\DeliveryStatus;
use AhsanHafeez\ShipmentTracking\Model\Webhook\InvalidPayloadException;
use AhsanHafeez\ShipmentTracking\Model\Webhook\PayloadParser;
use AhsanHafeez\ShipmentTracking\Model\Webhook\SignatureVerifier;
use PHPUnit\Framework\TestCase;

final class WebhookTest extends TestCase
{
    private const SECRET = 'whsec_test_123';
    private const NOW = 1791400000;

    public function testAcceptsValidSignature(): void
    {
        $body = '{"event_id":"evt_1"}';
        $header = 't=' . self::NOW . ',v1=' . SignatureVerifier::sign(self::NOW, $body, self::SECRET);

        self::assertTrue((new SignatureVerifier())->verify($header, $body, self::SECRET, self::NOW + 10));
    }

    public function testRejectsTamperedBodyWrongSecretAndReplay(): void
    {
        $verifier = new SignatureVerifier();
        $body = '{"event_id":"evt_1"}';
        $header = 't=' . self::NOW . ',v1=' . SignatureVerifier::sign(self::NOW, $body, self::SECRET);

        self::assertFalse($verifier->verify($header, '{"event_id":"evt_2"}', self::SECRET, self::NOW));
        self::assertFalse($verifier->verify($header, $body, 'other-secret', self::NOW));
        self::assertFalse($verifier->verify($header, $body, self::SECRET, self::NOW + 301), 'older than 5 minutes');
        self::assertFalse($verifier->verify($header, $body, '', self::NOW), 'no secret configured');
        self::assertFalse($verifier->verify('v1=abc', $body, self::SECRET, self::NOW), 'no timestamp');
    }

    public function testAcceptsAnyOfSeveralSignaturesDuringSecretRotation(): void
    {
        $body = '{}';
        $header = sprintf(
            't=%d,v1=%s,v1=%s',
            self::NOW,
            SignatureVerifier::sign(self::NOW, $body, 'old-secret'),
            SignatureVerifier::sign(self::NOW, $body, self::SECRET)
        );

        self::assertTrue((new SignatureVerifier())->verify($header, $body, self::SECRET, self::NOW));
    }

    public function testParsesPayload(): void
    {
        $event = (new PayloadParser())->parse(json_encode([
            'event_id' => 'evt_01',
            'tracking_number' => '1550 1234 5678 90',
            'carrier' => 'DPD',
            'status' => 'Out for delivery',
            'description' => 'With your driver',
            'location' => 'Birmingham',
            'occurred_at' => '2026-10-07T10:30:00+01:00',
        ]));

        self::assertSame('15501234567890', $event->trackingNumber);
        self::assertSame('dpd', $event->carrier);
        self::assertSame(DeliveryStatus::OUT_FOR_DELIVERY, $event->status);
        self::assertSame('Out for delivery', $event->rawStatus);
        self::assertSame('2026-10-07 09:30:00', $event->occurredAt->format('Y-m-d H:i:s'), 'stored in UTC');
    }

    /**
     * @dataProvider badPayloads
     */
    public function testRejectsBadPayloads(string $body, string $message): void
    {
        $this->expectException(InvalidPayloadException::class);
        $this->expectExceptionMessage($message);

        (new PayloadParser())->parse($body);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function badPayloads(): array
    {
        $valid = ['event_id' => 'e', 'tracking_number' => '1', 'status' => 'delivered', 'occurred_at' => '2026-10-07T10:00:00Z'];

        return [
            'not json' => ['{oops', 'not valid JSON'],
            'missing event id' => [json_encode(array_diff_key($valid, ['event_id' => 1])), '"event_id" is required'],
            'unknown status' => [json_encode(['status' => 'teleported'] + $valid), 'Unknown status'],
            'bad date' => [json_encode(['occurred_at' => 'yesterday-ish'] + $valid), 'ISO 8601'],
            'array instead of string' => [json_encode(['tracking_number' => ['x']] + $valid), 'must be a string'],
        ];
    }
}
