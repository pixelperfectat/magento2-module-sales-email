<?php

declare(strict_types=1);

namespace PixelPerfect\SalesEmail\ViewModel;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Product;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Sales\Model\Order\Item as OrderItem;
use Magento\Store\Model\ScopeInterface;
use PixelPerfect\SalesEmail\Api\Service\StoreEmulatorInterface;
use Psr\Log\LoggerInterface;

class EmailItem implements ArgumentInterface
{
    /**
     * @param StoreEmulatorInterface $emulator
     * @param ProductRepositoryInterface $productRepository
     * @param ImageHelper $imageHelper
     * @param LoggerInterface $logger
     * @param ScopeConfigInterface $scopeConfig
     * @param string $imageId view.xml image id used for the thumbnail
     * @param int $imageSize edge length in pixels of the resized thumbnail
     * @param int $maxQtyDecimals most fraction digits shown for a quantity
     */
    public function __construct(
        private readonly StoreEmulatorInterface $emulator,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ImageHelper $imageHelper,
        private readonly LoggerInterface $logger,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly string $imageId = 'product_thumbnail_image',
        private readonly int $imageSize = 128,
        private readonly int $maxQtyDecimals = 4
    ) {
    }

    /**
     * Absolute thumbnail URL of the ordered product, null when it has no image or no longer exists
     *
     * Built in the frontend environment of the order's store (see StoreEmulatorInterface).
     *
     * @param OrderItem $item
     * @param int $storeId
     * @return string|null
     */
    public function getImageUrl(OrderItem $item, int $storeId): ?string
    {
        $productId = (int)$item->getProductId();
        if ($productId === 0) {
            return null;
        }

        return $this->emulator->emulate($storeId, fn (): ?string => $this->buildUrl($productId, $storeId));
    }

    /**
     * Quantity in the locale of the store, without trailing zeros (2 -> "2", 1.5 -> "1,5" in de_DE)
     *
     * @param float $qty
     * @param int $storeId
     * @return string
     */
    public function formatQty(float $qty, int $storeId): string
    {
        $locale = (string)$this->scopeConfig->getValue('general/locale/code', ScopeInterface::SCOPE_STORE, $storeId);
        $formatter = new \NumberFormatter($locale !== '' ? $locale : 'en_US', \NumberFormatter::DECIMAL);
        $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, $this->maxQtyDecimals);
        $formatter->setAttribute(\NumberFormatter::GROUPING_USED, 0);

        return (string)$formatter->format($qty);
    }

    /**
     * Resize the product thumbnail and return its URL
     *
     * @param int $productId
     * @param int $storeId
     * @return string|null
     */
    private function buildUrl(int $productId, int $storeId): ?string
    {
        try {
            /** @var Product $product */
            $product = $this->productRepository->getById($productId, false, $storeId);
            $thumbnail = $product->getData('thumbnail');
            if (!is_string($thumbnail) || $thumbnail === '' || $thumbnail === 'no_selection') {
                return null;
            }
            $url = (string)$this->imageHelper->init($product, $this->imageId)
                ->resize($this->imageSize)
                ->getUrl();

            return $url !== '' ? $url : null;
        } catch (NoSuchEntityException) {
            return null;
        } catch (\Throwable $e) {
            $this->logger->warning('Email item image could not be built: ' . $e->getMessage());

            return null;
        }
    }
}
