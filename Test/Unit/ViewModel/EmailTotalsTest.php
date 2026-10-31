<?php

declare(strict_types=1);

namespace PixelPerfect\SalesEmail\Test\Unit\ViewModel;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Asset\Repository;
use Magento\Store\Model\App\Emulation;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PixelPerfect\SalesEmail\ViewModel\EmailTotals;
use Psr\Log\LoggerInterface;

class EmailTotalsTest extends TestCase
{
    private ScopeConfigInterface&MockObject $scopeConfig;
    private Emulation&MockObject $emulation;
    private Repository&MockObject $assets;
    private EmailTotals $viewModel;

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->emulation = $this->createMock(Emulation::class);
        $this->assets = $this->createMock(Repository::class);
        $this->viewModel = new EmailTotals(
            $this->scopeConfig,
            $this->emulation,
            $this->assets,
            $this->createMock(LoggerInterface::class)
        );
    }

    public function testNoIconWhenFlagIsOff(): void
    {
        $this->scopeConfig->method('isSetFlag')
            ->with('pp_discount_lines/documents/email_icon', 'store', 2)->willReturn(false);
        $this->assets->expects($this->never())->method('getUrl');

        $this->assertNull($this->viewModel->getIconUrl('coupon', 2));
    }

    public function testIconUrlWhenFlagIsOn(): void
    {
        $this->scopeConfig->method('isSetFlag')->willReturn(true);
        $this->assets->method('getUrl')->with('PixelPerfect_SalesEmail::images/coupon.png')
            ->willReturn('https://shop.example.com/static/coupon.png');
        $this->emulation->expects($this->once())->method('stopEnvironmentEmulation');

        $this->assertSame('https://shop.example.com/static/coupon.png', $this->viewModel->getIconUrl('coupon', 2));
    }

    public function testUnknownKindHasNoIcon(): void
    {
        $this->scopeConfig->method('isSetFlag')->willReturn(true);

        $this->assertNull($this->viewModel->getIconUrl('mystery', 2));
    }
}
