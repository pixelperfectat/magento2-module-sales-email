<?php

declare(strict_types=1);

namespace PixelPerfect\SalesEmail\Test\Unit\ViewModel;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Product;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model\Order\Item as OrderItem;
use Magento\Store\Model\App\Emulation;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PixelPerfect\SalesEmail\ViewModel\EmailItem;
use Psr\Log\LoggerInterface;

class EmailItemTest extends TestCase
{
    private Emulation&MockObject $emulation;
    private ProductRepositoryInterface&MockObject $productRepository;
    private ImageHelper&MockObject $imageHelper;
    private EmailItem $viewModel;

    protected function setUp(): void
    {
        $this->emulation = $this->createMock(Emulation::class);
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
        $this->imageHelper = $this->createMock(ImageHelper::class);
        $this->viewModel = new EmailItem(
            $this->emulation,
            $this->productRepository,
            $this->imageHelper,
            $this->createMock(LoggerInterface::class)
        );
    }

    private function item(int $productId): OrderItem&MockObject
    {
        $item = $this->createMock(OrderItem::class);
        $item->method('getProductId')->willReturn($productId);

        return $item;
    }

    private function product(?string $thumbnail): Product&MockObject
    {
        $product = $this->createMock(Product::class);
        $product->method('getData')->with('thumbnail')->willReturn($thumbnail);

        return $product;
    }

    public function testNoImageReturnsNull(): void
    {
        $this->productRepository->method('getById')->willReturn($this->product('no_selection'));
        $this->imageHelper->expects($this->never())->method('init');

        $this->assertNull($this->viewModel->getImageUrl($this->item(5), 1));
    }

    public function testEmptyThumbnailReturnsNull(): void
    {
        $this->productRepository->method('getById')->willReturn($this->product(null));

        $this->assertNull($this->viewModel->getImageUrl($this->item(5), 1));
    }

    public function testDeletedProductReturnsNull(): void
    {
        $this->productRepository->method('getById')->willThrowException(new NoSuchEntityException());

        $this->assertNull($this->viewModel->getImageUrl($this->item(5), 1));
    }

    public function testItemWithoutProductIdReturnsNullWithoutEmulation(): void
    {
        $this->emulation->expects($this->never())->method('startEnvironmentEmulation');

        $this->assertNull($this->viewModel->getImageUrl($this->item(0), 1));
    }

    public function testAbsoluteUrlOfResizedThumbnail(): void
    {
        $product = $this->product('/t/e/tea.jpg');
        $this->productRepository->method('getById')->with(5, false, 3)->willReturn($product);
        $this->imageHelper->expects($this->once())->method('init')
            ->with($product, 'product_thumbnail_image')->willReturnSelf();
        $this->imageHelper->expects($this->once())->method('resize')->with(128)->willReturnSelf();
        $this->imageHelper->method('getUrl')->willReturn('https://shop.example.com/media/tea-128.jpg');

        $this->assertSame(
            'https://shop.example.com/media/tea-128.jpg',
            $this->viewModel->getImageUrl($this->item(5), 3)
        );
    }

    public function testEmulationIsAlwaysStoppedEvenOnFailure(): void
    {
        $this->emulation->expects($this->once())->method('startEnvironmentEmulation')->with(3, 'frontend', true);
        $this->emulation->expects($this->once())->method('stopEnvironmentEmulation');
        $this->productRepository->method('getById')->willThrowException(new \RuntimeException('boom'));

        $this->assertNull($this->viewModel->getImageUrl($this->item(5), 3));
    }
}
