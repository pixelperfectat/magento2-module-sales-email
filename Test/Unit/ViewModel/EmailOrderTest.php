<?php

declare(strict_types=1);

namespace PixelPerfect\SalesEmail\Test\Unit\ViewModel;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PixelPerfect\SalesEmail\ViewModel\EmailOrder;

class EmailOrderTest extends TestCase
{
    private OrderRepositoryInterface&MockObject $repository;
    private ScopeConfigInterface&MockObject $scopeConfig;
    private TimezoneInterface&MockObject $timezone;
    private EmailOrder $viewModel;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(OrderRepositoryInterface::class);
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->timezone = $this->createMock(TimezoneInterface::class);
        $this->viewModel = new EmailOrder(
            $this->repository,
            $this->scopeConfig,
            $this->timezone
        );
    }

    public function testFormatsInTheStoreLocaleAndTimezone(): void
    {
        $order = $this->createMock(Order::class);
        $order->method('getStoreId')->willReturn(2);
        $order->method('getCreatedAt')->willReturn('2026-10-31 15:00:00');
        $this->repository->method('get')->with(5)->willReturn($order);
        $this->scopeConfig->method('getValue')->with('general/locale/code', 'store', 2)->willReturn('de_DE');
        $this->timezone->method('getConfigTimezone')->with('store', '2')->willReturn('Europe/Vienna');
        $this->timezone->expects($this->once())->method('formatDateTime')
            ->with('2026-10-31 15:00:00', 2, 2, 'de_DE', 'Europe/Vienna')
            ->willReturn('31.10.2026, 16:00:00');

        $this->assertSame('31.10.2026, 16:00:00', $this->viewModel->getPlacedAt(5));
    }

    public function testMissingOrderGivesEmptyString(): void
    {
        $this->repository->method('get')->willThrowException(new NoSuchEntityException());

        $this->assertSame('', $this->viewModel->getPlacedAt(5));
    }
}
