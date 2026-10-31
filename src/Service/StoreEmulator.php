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
     *
     * Emulation is forced because the store can already match while the design area differs (admin send). An outer
     * emulation of a different store is not restored by a nested stop (core keeps one saved state), so callers must
     * not rely on the outer store being active after this call when they were already inside another emulation.
     */
    public function emulate(int $storeId, callable $callback): mixed
    {
        if ((int)$this->storeManager->getStore()->getId() === $storeId
            && $this->design->getArea() === Area::AREA_FRONTEND
        ) {
            return $callback();
        }

        $this->emulation->startEnvironmentEmulation($storeId, Area::AREA_FRONTEND, true);
        try {
            return $callback();
        } finally {
            $this->emulation->stopEnvironmentEmulation();
        }
    }
}
