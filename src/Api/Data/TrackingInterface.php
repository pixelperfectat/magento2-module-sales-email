<?php declare(strict_types=1);

namespace PixelPerfect\SalesEmail\Api\Data;

interface TrackingInterface
{
    /**
     * Carrier tracking number
     *
     * @return string
     */
    public function getNumber(): string;

    /**
     * Carrier title as entered with the shipment, empty when unknown
     *
     * @return string
     */
    public function getCarrierTitle(): string;

    /**
     * Absolute tracking URL for the shipment's store, null when there is none
     *
     * @return string|null
     */
    public function getUrl(): ?string;
}
