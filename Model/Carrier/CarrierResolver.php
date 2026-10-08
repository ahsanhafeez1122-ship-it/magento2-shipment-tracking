<?php
declare(strict_types=1);

namespace AhsanHafeez\ShipmentTracking\Model\Carrier;

/**
 * Works out which carrier a shipment track belongs to.
 *
 * Magento stores a carrier code ("dpd", "ups", or "custom") plus a free-text title.
 * Warehouses often use "custom" with titles like "DPD Local Next Day", which is why
 * so many tracking emails go out without a link. The title is matched against aliases.
 */
class CarrierResolver
{
    /**
     * @param array<string, Carrier> $carriers
     */
    public function resolve(array $carriers, string $carrierCode, string $title): ?Carrier
    {
        $code = strtolower(trim($carrierCode));
        if ($code !== '' && $code !== 'custom' && isset($carriers[$code])) {
            return $carriers[$code];
        }

        $name = Carrier::normalizeName($title);
        if ($name === '') {
            return null;
        }

        foreach ($carriers as $carrier) {
            if (in_array($name, $carrier->aliases, true)) {
                return $carrier;
            }
        }

        // "DPD Local Next Day" → longest alias contained in the title wins, so "dpdlocal" beats "dpd".
        $best = null;
        $bestLength = 0;
        foreach ($carriers as $carrier) {
            foreach ($carrier->aliases as $alias) {
                if (strlen($alias) >= 3 && strlen($alias) > $bestLength && str_contains($name, $alias)) {
                    $best = $carrier;
                    $bestLength = strlen($alias);
                }
            }
        }

        return $best;
    }
}
