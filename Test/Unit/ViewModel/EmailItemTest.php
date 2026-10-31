<?php

declare(strict_types=1);

namespace PixelPerfect\SalesEmail\Test\Unit\ViewModel;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Product;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model\Order\Item as OrderItem;
use PixelPerfect\SalesEmail\Api\Service\StoreEmulatorInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PixelPerfect\SalesEmail\ViewModel\EmailItem;
use Psr\Log\LoggerInterface;

class EmailItemTest extends TestCase
{
    private StoreEmulatorInterface&MockObject $emulator;
    private ProductRepositoryInterface&MockObject $productRepository;
    private ImageHelper&MockObject $imageHelper;
    private \Magento\Framework\App\Config\ScopeConfigInterface&MockObject $scopeConfig;
    private EmailItem $viewModel;

    protected function setUp(): void
    {
        $this->emulator = $this->createMock(StoreEmulatorInterface::class);
        $this->emulator->method('emulate')->willReturnCallback(static fn (int $storeId, callable $cb) => $cb());
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
        $this->imageHelper = $this->createMock(ImageHelper::class);
        $this->scopeConfig = $this->createMock(\Magento\Framework\App\Config\ScopeConfigInterface::class);
        $this->viewModel = new EmailItem(
            $this->emulator,
            $this->productRepository,
            $this->imageHelper,
            $this->createMock(LoggerInterface::class),
            $this->scopeConfig
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
        $this->emulator->expects($this->never())->method('emulate');

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

    public function testUrlIsBuiltForTheOrdersStore(): void
    {
        $this->emulator = $this->createMock(StoreEmulatorInterface::class);
        $this->emulator->expects($this->once())->method('emulate')->with(3)->willReturn('https://shop.example.com/i.jpg');
        $viewModel = new EmailItem(
            $this->emulator,
            $this->productRepository,
            $this->imageHelper,
            $this->createMock(LoggerInterface::class),
            $this->scopeConfig
        );

        $this->assertSame('https://shop.example.com/i.jpg', $viewModel->getImageUrl($this->item(5), 3));
    }

    public function testFailureReturnsNull(): void
    {
        $this->productRepository->method('getById')->willThrowException(new \RuntimeException('boom'));

        $this->assertNull($this->viewModel->getImageUrl($this->item(5), 3));
    }

    public function testQuantityIsLocalisedWithoutTrailingZeros(): void
    {
        $this->scopeConfig->method('getValue')->willReturnMap([
            ['general/locale/code', 'store', 1, 'en_US'],
            ['general/locale/code', 'store', 2, 'de_DE'],
        ]);

        $this->assertSame('2', $this->viewModel->formatQty(2.0, 1));
        $this->assertSame('1.5', $this->viewModel->formatQty(1.5, 1));
        $this->assertSame('1,5', $this->viewModel->formatQty(1.5, 2));
    }
}
