<?php
declare(strict_types=1);

namespace AhsanHafeez\ShipmentTracking\Model;

final class TrackLink
{
    public function __construct(
        public readonly string $carrierLabel,
        public readonly string $number,
        public readonly ?string $url,
        public readonly ?string $deliveryStatus,
        public readonly bool $numberLooksValid
    ) {
    }
}
