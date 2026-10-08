<?php
declare(strict_types=1);

namespace AhsanHafeez\ShipmentTracking\Model\Carrier;

/**
 * One configured carrier: how to build its tracking URL and how to recognise it.
 */
final class Carrier
{
    /**
     * @param string[] $aliases already normalised with Carrier::normalizeName()
     */
    public function __construct(
        public readonly string $code,
        public readonly string $label,
        public readonly string $urlTemplate,
        public readonly ?string $numberPattern = null,
        public readonly array $aliases = []
    ) {
    }

    public function buildUrl(string $trackingNumber, string $postcode = ''): string
    {
        return strtr($this->urlTemplate, [
            '{number}' => rawurlencode(self::normalizeNumber($trackingNumber)),
            '{postcode}' => rawurlencode(strtoupper(str_replace(' ', '', $postcode))),
        ]);
    }

    /**
     * True when no pattern is configured: the pattern is only for spotting typos, never for hiding a link.
     */
    public function looksValid(string $trackingNumber): bool
    {
        if ($this->numberPattern === null) {
            return true;
        }

        return preg_match('/' . str_replace('/', '\/', $this->numberPattern) . '/', self::normalizeNumber($trackingNumber)) === 1;
    }

    /**
     * Warehouse staff paste numbers with spaces and lowercase letters: "jd 0002 1234" → "JD00021234".
     */
    public static function normalizeNumber(string $number): string
    {
        return strtoupper((string) preg_replace('/[\s-]+/', '', $number));
    }

    /**
     * "DPD Local (Next Day)" → "dpdlocalnextday", for matching free-text carrier titles.
     */
    public static function normalizeName(string $name): string
    {
        return (string) preg_replace('/[^a-z0-9]/', '', strtolower($name));
    }
}
