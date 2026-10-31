<?php

declare(strict_types=1);

namespace PixelPerfect\SalesEmail\Service;

use Magento\Framework\App\Area;
use Magento\Framework\View\DesignInterface;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\StoreManagerInterface;
use PixelPerfect\SalesEmail\Api\Service\StoreEmulatorInterface;

class StoreEmulator implements StoreEmulatorInterface
{
    /**
     * @param Emulation $emulation
     * @param StoreManagerInterface $storeManager
     * @param DesignInterface $design
     */
    public function __construct(
        private readonly Emulation $emulation,
        private readonly StoreManagerInterface $storeManager,
        private readonly DesignInterface $design
    ) {
    }

    /**
     * @inheritDoc
     */
    public function emulate(int $storeId, callable $callback): mixed
    {
        if ((int)$this->storeManager->getStore()->getId() === $storeId
            && $this->design->getArea() === Area::AREA_FRONTEND
        ) {
            return $callback();
        }

        $this->emulation->startEnvironmentEmulation($storeId, Area::AREA_FRONTEND);
        try {
            return $callback();
        } finally {
            $this->emulation->stopEnvironmentEmulation();
        }
    }
}
