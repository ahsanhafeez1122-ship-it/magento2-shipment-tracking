<?php
declare(strict_types=1);

namespace AhsanHafeez\ShipmentTracking\Model\Webhook;

use DateTimeImmutable;

final class TrackingEvent
{
    public function __construct(
        public readonly string $externalId,
        public readonly string $trackingNumber,
        public readonly ?string $carrier,
        public readonly string $status,
        public readonly string $rawStatus,
        public readonly ?string $description,
        public readonly ?string $location,
        public readonly DateTimeImmutable $occurredAt
    ) {
    }
}
