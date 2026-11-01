<?php

declare(strict_types=1);

namespace PixelPerfect\SalesEmail\ViewModel;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Magento\Sales\Model\Order\Shipment;
use Magento\Sales\Model\Order\Shipment\Track;
use Magento\Shipping\Helper\Data as ShippingHelper;
use PixelPerfect\SalesEmail\Api\Data\TrackingInterface;
use PixelPerfect\SalesEmail\Api\Data\TrackingInterfaceFactory;
use Psr\Log\LoggerInterface;

class ShipmentTracking implements ArgumentInterface
{
    /**
     * @param ShipmentRepositoryInterface $shipmentRepository
     * @param ShippingHelper $shippingHelper
     * @param TrackingInterfaceFactory $trackingFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly ShipmentRepositoryInterface $shipmentRepository,
        private readonly ShippingHelper $shippingHelper,
        private readonly TrackingInterfaceFactory $trackingFactory,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Tracking entries of a shipment, one per track, empty when the shipment has none
     *
     * The URL is the core tracking popup of the shipment's store (absolute); it is null when core gives none.
     *
     * @param int $shipmentId
     * @return TrackingInterface[]
     */
    public function getTracks(int $shipmentId): array
    {
        try {
            /** @var Shipment $shipment */
            $shipment = $this->shipmentRepository->get($shipmentId);
        } catch (NoSuchEntityException) {
            return [];
        }

        $result = [];
        /** @var Track $track */
        foreach ($shipment->getAllTracks() as $track) {
            $number = trim((string)$track->getTrackNumber());
            if ($number === '') {
                continue;
            }
            $result[] = $this->trackingFactory->create([
                'number' => $number,
                'carrierTitle' => trim((string)$track->getTitle()),
                'url' => $this->getUrl($track),
            ]);
        }

        return $result;
    }

    /**
     * Core tracking popup URL of a track, null when unavailable
     *
     * @param Track $track
     * @return string|null
     */
    private function getUrl(Track $track): ?string
    {
        try {
            $url = trim((string)$this->shippingHelper->getTrackingPopupUrlBySalesModel($track));
        } catch (\Exception $e) {
            $this->logger->warning('Tracking URL could not be built: ' . $e->getMessage());

            return null;
        }

        return $url !== '' ? $url : null;
    }
}
