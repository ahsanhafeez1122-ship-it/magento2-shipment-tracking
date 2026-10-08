<?php
declare(strict_types=1);

namespace AhsanHafeez\ShipmentTracking\Service;

use AhsanHafeez\ShipmentTracking\Model\ResourceModel\TrackingEvent as EventResource;
use AhsanHafeez\ShipmentTracking\Model\Status\DeliveryStatus;
use AhsanHafeez\ShipmentTracking\Model\Webhook\TrackingEvent;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/**
 * Stores a tracking event once and moves the track's delivery status forward.
 * Safe to receive the same event twice and safe to receive events out of order.
 */
class EventProcessor
{
    public const RESULT_APPLIED = 'applied';
    public const RESULT_STORED = 'stored';
    public const RESULT_DUPLICATE = 'duplicate';
    public const RESULT_UNKNOWN_TRACK = 'unknown_track';

    public function __construct(private readonly EventResource $resource)
    {
    }

    public function process(TrackingEvent $event): string
    {
        $track = $this->resource->findTrack($event->trackingNumber);
        if ($track === null) {
            return self::RESULT_UNKNOWN_TRACK;
        }

        $trackId = (int) $track['entity_id'];
        $connection = $this->resource->getConnection();
        $connection->beginTransaction();
        try {
            if (!$this->resource->insertEvent($trackId, $event)) {
                $connection->rollBack();
                return self::RESULT_DUPLICATE;
            }

            $currentAt = $track['delivery_status_at']
                ? new DateTimeImmutable((string) $track['delivery_status_at'], new DateTimeZone('UTC'))
                : null;

            $apply = DeliveryStatus::shouldReplace($track['delivery_status'] ?: null, $currentAt, $event->status, $event->occurredAt);
            if ($apply) {
                $this->resource->updateTrackStatus($trackId, $event->status, $event->occurredAt);
            }

            $connection->commit();
        } catch (Throwable $e) {
            $connection->rollBack();
            throw $e;
        }

        return $apply ? self::RESULT_APPLIED : self::RESULT_STORED;
    }
}
