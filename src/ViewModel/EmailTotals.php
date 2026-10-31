<?php

declare(strict_types=1);

namespace PixelPerfect\SalesEmail\ViewModel;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Asset\Repository;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\ScopeInterface;
use PixelPerfect\SalesEmail\Api\Service\StoreEmulatorInterface;
use Psr\Log\LoggerInterface;

class EmailTotals implements ArgumentInterface
{
    /**
     * Config flag of the discount-lines package, read by path only (no class dependency)
     */
    private const XML_PATH_EMAIL_ICON = 'pp_discount_lines/documents/email_icon';

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreEmulatorInterface $emulator
     * @param Repository $assetRepository
     * @param LoggerInterface $logger
     * @param array<string, string> $icons discount line kind => icon file name in web/images
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly StoreEmulatorInterface $emulator,
        private readonly Repository $assetRepository,
        private readonly LoggerInterface $logger,
        private readonly array $icons = [
            'coupon' => 'coupon.png',
            'free_shipping' => 'coupon.png',
            'automatic' => 'promotion.png',
            'gift' => 'gift.png',
            'gift_card' => 'gift.png',
        ]
    ) {
    }

    /**
     * Absolute URL of the PNG icon for a discount line kind, null when icons are off or the kind has none
     *
     * @param string $kind
     * @param int $storeId
     * @return string|null
     */
    public function getIconUrl(string $kind, int $storeId): ?string
    {
        if (!isset($this->icons[$kind])
            || !$this->scopeConfig->isSetFlag(self::XML_PATH_EMAIL_ICON, ScopeInterface::SCOPE_STORE, $storeId)
        ) {
            return null;
        }

        return $this->emulator->emulate($storeId, function () use ($kind): ?string {
            try {
                return $this->assetRepository->getUrl('PixelPerfect_SalesEmail::images/' . $this->icons[$kind]);
            } catch (\Throwable $e) {
                $this->logger->warning('Email discount icon could not be built: ' . $e->getMessage());

                return null;
            }
        });
    }
}
