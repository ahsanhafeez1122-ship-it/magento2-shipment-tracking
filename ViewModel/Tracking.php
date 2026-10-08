<?php
declare(strict_types=1);

namespace AhsanHafeez\ShipmentTracking\ViewModel;

use AhsanHafeez\ShipmentTracking\Model\Config;
use AhsanHafeez\ShipmentTracking\Model\Status\DeliveryStatus;
use AhsanHafeez\ShipmentTracking\Model\TrackLink;
use AhsanHafeez\ShipmentTracking\Model\TrackLinkResolver;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Shipment;

/**
 * Used by the shipment email and the customer account order page.
 */
class Tracking implements ArgumentInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly TrackLinkResolver $linkResolver,
        private readonly ShipmentRepositoryInterface $shipmentRepository
    ) {
    }

    public function resolveShipment(mixed $shipment, mixed $shipmentId): ?Shipment
    {
        if ($shipment instanceof Shipment) {
            return $shipment;
        }
        if (!$shipmentId) {
            return null;
        }
        try {
            $loaded = $this->shipmentRepository->get((int) $shipmentId);
        } catch (\Magento\Framework\Exception\NoSuchEntityException) {
            return null;
        }

        return $loaded instanceof Shipment ? $loaded : null;
    }

    public function isEnabled(?int $storeId = null): bool
    {
        return $this->config->isEnabled($storeId);
    }

    /**
     * @return TrackLink[]
     */
    public function getLinksForShipment(Shipment $shipment): array
    {
        $order = $shipment->getOrder();
        $storeId = (int) $shipment->getStoreId();
        $links = [];
        foreach ($shipment->getAllTracks() as $track) {
            $links[] = $this->linkResolver->resolve($track, $order, $storeId);
        }

        return $links;
    }

    /**
     * @return TrackLink[]
     */
    public function getLinksForOrder(Order $order): array
    {
        $links = [];
        foreach ($order->getTracksCollection() as $track) {
            $links[] = $this->linkResolver->resolve($track, $order, (int) $order->getStoreId());
        }

        return $links;
    }

    public function getStatusLabel(?string $status): string
    {
        return $status ? (string) __(DeliveryStatus::label($status)) : '';
    }
}
