<?php

declare(strict_types=1);

namespace PixelPerfect\SalesEmail\ViewModel;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Product;
use Magento\Framework\App\Area;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Sales\Model\Order\Item as OrderItem;
use Magento\Store\Model\App\Emulation;
use Psr\Log\LoggerInterface;

class EmailItem implements ArgumentInterface
{
    /**
     * @param Emulation $emulation
     * @param ProductRepositoryInterface $productRepository
     * @param ImageHelper $imageHelper
     * @param LoggerInterface $logger
     * @param string $imageId view.xml image id used for the thumbnail
     * @param int $imageSize edge length in pixels of the resized thumbnail
     */
    public function __construct(
        private readonly Emulation $emulation,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ImageHelper $imageHelper,
        private readonly LoggerInterface $logger,
        private readonly string $imageId = 'product_thumbnail_image',
        private readonly int $imageSize = 128
    ) {
    }

    /**
     * Absolute thumbnail URL of the ordered product, null when it has no image or no longer exists
     *
     * Built under frontend emulation of the order's store, so it is correct in cron and queue contexts.
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

        $this->emulation->startEnvironmentEmulation($storeId, Area::AREA_FRONTEND, true);
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
        } finally {
            $this->emulation->stopEnvironmentEmulation();
        }
    }
}
