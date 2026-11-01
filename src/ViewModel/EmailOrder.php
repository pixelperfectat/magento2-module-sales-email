<?php

declare(strict_types=1);

namespace PixelPerfect\SalesEmail\ViewModel;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Store\Model\ScopeInterface;

class EmailOrder implements ArgumentInterface
{
    /**
     * @param OrderRepositoryInterface $orderRepository
     * @param ScopeConfigInterface $scopeConfig
     * @param TimezoneInterface $timezone
     * @param int $dateFormat IntlDateFormatter style of date and time (2 = medium)
     */
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly TimezoneInterface $timezone,
        private readonly int $dateFormat = 2
    ) {
    }

    /**
     * Order date in the locale and time zone of the order's store
     *
     * The OrderSender builds its created_at_formatted variable with the locale of the caller, so an email sent
     * from admin carries the admin locale; the store's configured locale is used here instead.
     *
     * @param int $orderId
     * @return string
     */
    public function getPlacedAt(int $orderId): string
    {
        try {
            $order = $this->orderRepository->get($orderId);
        } catch (NoSuchEntityException) {
            return '';
        }

        $storeId = (int)$order->getStoreId();

        return $this->timezone->formatDateTime(
            (string)$order->getCreatedAt(),
            $this->dateFormat,
            $this->dateFormat,
            (string)$this->scopeConfig->getValue('general/locale/code', ScopeInterface::SCOPE_STORE, $storeId),
            $this->timezone->getConfigTimezone(ScopeInterface::SCOPE_STORE, (string)$storeId)
        );
    }
}
