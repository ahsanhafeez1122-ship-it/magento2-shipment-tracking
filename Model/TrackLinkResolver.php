<?php
declare(strict_types=1);

namespace AhsanHafeez\ShipmentTracking\Model;

use AhsanHafeez\ShipmentTracking\Model\Carrier\Carrier;
use AhsanHafeez\ShipmentTracking\Model\Carrier\CarrierResolver;
use Magento\Sales\Api\Data\ShipmentTrackInterface;
use Magento\Sales\Model\Order;

/**
 * Track row → label, clean number, link and delivery status, ready for a template or email.
 */
class TrackLinkResolver
{
    public function __construct(
        private readonly CarrierProvider $carrierProvider,
        private readonly CarrierResolver $carrierResolver
    ) {
    }

    public function resolve(ShipmentTrackInterface $track, ?Order $order = null, ?int $storeId = null): TrackLink
    {
        $number = Carrier::normalizeNumber((string) $track->getTrackNumber());
        $carrier = $this->carrierResolver->resolve(
            $this->carrierProvider->getCarriers($storeId),
            (string) $track->getCarrierCode(),
            (string) $track->getTitle()
        );

        $postcode = '';
        if ($order !== null && $order->getShippingAddress() !== null) {
            $postcode = (string) $order->getShippingAddress()->getPostcode();
        }

        $status = $track instanceof \Magento\Framework\DataObject ? $track->getData('delivery_status') : null;

        return new TrackLink(
            $carrier?->label ?? (string) $track->getTitle(),
            $number,
            $carrier && $number !== '' ? $carrier->buildUrl($number, $postcode) : null,
            $status ? (string) $status : null,
            $carrier ? $carrier->looksValid($number) : true
        );
    }
}
