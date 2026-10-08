<?php
declare(strict_types=1);

namespace AhsanHafeez\ShipmentTracking\Model\Status;

/**
 * Carrier-neutral delivery statuses, and the rules for moving between them.
 */
final class DeliveryStatus
{
    public const LABEL_CREATED = 'label_created';
    public const IN_TRANSIT = 'in_transit';
    public const OUT_FOR_DELIVERY = 'out_for_delivery';
    public const DELIVERED = 'delivered';
    public const EXCEPTION = 'exception';
    public const RETURNED = 'returned_to_sender';

    /** Higher = further along. Exception sits beside the normal flow. */
    private const RANK = [
        self::LABEL_CREATED => 0,
        self::IN_TRANSIT => 1,
        self::EXCEPTION => 1,
        self::OUT_FOR_DELIVERY => 2,
        self::DELIVERED => 3,
        self::RETURNED => 3,
    ];

    private const LABELS = [
        self::LABEL_CREATED => 'Label created',
        self::IN_TRANSIT => 'In transit',
        self::OUT_FOR_DELIVERY => 'Out for delivery',
        self::DELIVERED => 'Delivered',
        self::EXCEPTION => 'Delivery problem',
        self::RETURNED => 'Returned to sender',
    ];

    /**
     * Raw carrier statuses seen in DPD, Royal Mail, Evri and aggregator feeds, after normalisation.
     */
    private const ALIASES = [
        'labelcreated' => self::LABEL_CREATED,
        'inforeceived' => self::LABEL_CREATED,
        'manifested' => self::LABEL_CREATED,
        'pending' => self::LABEL_CREATED,
        'collected' => self::IN_TRANSIT,
        'intransit' => self::IN_TRANSIT,
        'atdepot' => self::IN_TRANSIT,
        'athub' => self::IN_TRANSIT,
        'arrivedatdepot' => self::IN_TRANSIT,
        'outfordelivery' => self::OUT_FOR_DELIVERY,
        'withcourier' => self::OUT_FOR_DELIVERY,
        'ontheway' => self::OUT_FOR_DELIVERY,
        'delivered' => self::DELIVERED,
        'deliveredtoneighbour' => self::DELIVERED,
        'deliveredtosafeplace' => self::DELIVERED,
        'collectedfrompickupshop' => self::DELIVERED,
        'exception' => self::EXCEPTION,
        'failedattempt' => self::EXCEPTION,
        'deliveryfailed' => self::EXCEPTION,
        'addressissue' => self::EXCEPTION,
        'damaged' => self::EXCEPTION,
        'returnedtosender' => self::RETURNED,
        'returned' => self::RETURNED,
    ];

    public static function fromRaw(string $raw): ?string
    {
        $key = (string) preg_replace('/[^a-z]/', '', strtolower($raw));

        return self::ALIASES[$key] ?? (isset(self::RANK[strtolower($raw)]) ? strtolower($raw) : null);
    }

    public static function label(string $status): string
    {
        return self::LABELS[$status] ?? ucfirst(str_replace('_', ' ', $status));
    }

    /**
     * Should an incoming status replace the current one?
     * Webhooks arrive out of order, so an older "in transit" must not overwrite "delivered".
     * A newer event of equal rank wins (e.g. exception → in transit again after a re-attempt).
     */
    public static function shouldReplace(?string $current, ?\DateTimeImmutable $currentAt, string $incoming, \DateTimeImmutable $incomingAt): bool
    {
        if ($current === null || $currentAt === null) {
            return true;
        }
        if (in_array($current, [self::DELIVERED, self::RETURNED], true)) {
            return false;
        }

        $currentRank = self::RANK[$current] ?? 0;
        $incomingRank = self::RANK[$incoming] ?? 0;

        if ($incomingRank > $currentRank) {
            return true;
        }

        return $incomingRank === $currentRank && $incomingAt > $currentAt;
    }
}
