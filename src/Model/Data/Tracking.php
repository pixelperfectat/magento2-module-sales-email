<?php declare(strict_types=1);

namespace PixelPerfect\SalesEmail\Model\Data;

use PixelPerfect\SalesEmail\Api\Data\TrackingInterface;

readonly class Tracking implements TrackingInterface
{
    /**
     * @param string $number
     * @param string $carrierTitle
     * @param string|null $url
     */
    public function __construct(
        private string $number = '',
        private string $carrierTitle = '',
        private ?string $url = null
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getNumber(): string
    {
        return $this->number;
    }

    /**
     * @inheritDoc
     */
    public function getCarrierTitle(): string
    {
        return $this->carrierTitle;
    }

    /**
     * @inheritDoc
     */
    public function getUrl(): ?string
    {
        return $this->url;
    }
}
