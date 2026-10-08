<?php
declare(strict_types=1);

namespace AhsanHafeez\ShipmentTracking\Model\ResourceModel;

use AhsanHafeez\ShipmentTracking\Model\Webhook\TrackingEvent as Event;
use Magento\Framework\DB\Adapter\DuplicateException;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class TrackingEvent extends AbstractDb
{
    public const TABLE = 'ahsan_tracking_event';

    protected function _construct(): void
    {
        $this->_init(self::TABLE, 'entity_id');
    }

    /**
     * Find the track a carrier number belongs to. Numbers are compared without spaces, uppercase.
     *
     * @return array{entity_id:int, carrier_code:string, title:string, delivery_status:?string, delivery_status_at:?string}|null
     */
    public function findTrack(string $normalizedNumber): ?array
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getTable('sales_shipment_track'), ['entity_id', 'carrier_code', 'title', 'delivery_status', 'delivery_status_at'])
            ->where("UPPER(REPLACE(REPLACE(track_number, ' ', ''), '-', '')) = ?", $normalizedNumber)
            ->order('entity_id DESC')
            ->limit(1);

        $row = $connection->fetchRow($select);

        return $row ?: null;
    }

    /**
     * @return bool false when this external event id was already stored (duplicate delivery)
     */
    public function insertEvent(int $trackId, Event $event): bool
    {
        try {
            $this->getConnection()->insert($this->getMainTable(), [
                'track_id' => $trackId,
                'external_id' => $event->externalId,
                'status' => $event->status,
                'raw_status' => $event->rawStatus,
                'description' => $event->description,
                'location' => $event->location,
                'occurred_at' => $event->occurredAt->format('Y-m-d H:i:s'),
            ]);
        } catch (DuplicateException) {
            return false;
        }

        return true;
    }

    public function updateTrackStatus(int $trackId, string $status, \DateTimeImmutable $at): void
    {
        $this->getConnection()->update(
            $this->getTable('sales_shipment_track'),
            ['delivery_status' => $status, 'delivery_status_at' => $at->format('Y-m-d H:i:s')],
            ['entity_id = ?' => $trackId]
        );
    }

    /**
     * @return array<int, array<string, mixed>> newest first
     */
    public function getEventsForTrack(int $trackId): array
    {
        $connection = $this->getConnection();

        return $connection->fetchAll(
            $connection->select()
                ->from($this->getMainTable(), ['status', 'description', 'location', 'occurred_at'])
                ->where('track_id = ?', $trackId)
                ->order('occurred_at DESC')
        );
    }
}
