<?php
declare(strict_types=1);

namespace AhsanHafeez\ShipmentTracking\Model\Webhook;

use AhsanHafeez\ShipmentTracking\Model\Carrier\Carrier;
use AhsanHafeez\ShipmentTracking\Model\Status\DeliveryStatus;
use DateTimeImmutable;
use DateTimeZone;
use Exception;

/**
 * Turns the webhook JSON body into a TrackingEvent.
 *
 * {
 *   "event_id": "evt_01J9...",            required, unique per event
 *   "tracking_number": "15501234567890",  required
 *   "carrier": "dpd",                      optional
 *   "status": "OUT_FOR_DELIVERY",          required, carrier or normalised status
 *   "description": "With driver",          optional
 *   "location": "Birmingham Hub",          optional
 *   "occurred_at": "2026-10-07T09:30:00Z"  required, ISO 8601
 * }
 */
class PayloadParser
{
    public function parse(string $body): TrackingEvent
    {
        try {
            $data = json_decode($body, true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new InvalidPayloadException('Body is not valid JSON.');
        }
        if (!is_array($data)) {
            throw new InvalidPayloadException('Body must be a JSON object.');
        }

        $externalId = $this->string($data, 'event_id', 128, true);
        $number = Carrier::normalizeNumber($this->string($data, 'tracking_number', 64, true));
        $rawStatus = $this->string($data, 'status', 64, true);

        $status = DeliveryStatus::fromRaw($rawStatus);
        if ($status === null) {
            throw new InvalidPayloadException(sprintf('Unknown status "%s".', $rawStatus));
        }

        try {
            $occurredAt = (new DateTimeImmutable($this->string($data, 'occurred_at', 40, true)))
                ->setTimezone(new DateTimeZone('UTC'));
        } catch (Exception) {
            throw new InvalidPayloadException('occurred_at must be an ISO 8601 date.');
        }

        $carrier = $this->string($data, 'carrier', 32, false);

        return new TrackingEvent(
            $externalId,
            $number,
            $carrier !== '' ? strtolower($carrier) : null,
            $status,
            $rawStatus,
            $this->string($data, 'description', 255, false) ?: null,
            $this->string($data, 'location', 255, false) ?: null,
            $occurredAt
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function string(array $data, string $key, int $maxLength, bool $required): string
    {
        $value = $data[$key] ?? null;
        if ($value === null || $value === '') {
            if ($required) {
                throw new InvalidPayloadException(sprintf('"%s" is required.', $key));
            }
            return '';
        }
        if (!is_string($value) && !is_int($value)) {
            throw new InvalidPayloadException(sprintf('"%s" must be a string.', $key));
        }

        $value = trim((string) $value);
        if (mb_strlen($value) > $maxLength) {
            throw new InvalidPayloadException(sprintf('"%s" is longer than %d characters.', $key, $maxLength));
        }

        return $value;
    }
}
