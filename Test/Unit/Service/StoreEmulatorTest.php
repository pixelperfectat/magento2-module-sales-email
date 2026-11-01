<?php

declare(strict_types=1);

namespace PixelPerfect\SalesEmail\Test\Unit\Service;

use Magento\Framework\View\DesignInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PixelPerfect\SalesEmail\Service\StoreEmulator;

class StoreEmulatorTest extends TestCase
{
    private Emulation&MockObject $emulation;
    private StoreManagerInterface&MockObject $storeManager;
    private DesignInterface&MockObject $design;
    private StoreEmulator $emulator;

    protected function setUp(): void
    {
        $this->emulation = $this->createMock(Emulation::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->design = $this->createMock(DesignInterface::class);
        $this->emulator = new StoreEmulator($this->emulation, $this->storeManager, $this->design);
    }

    private function currentStore(int $id, string $area): void
    {
        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn($id);
        $this->storeManager->method('getStore')->willReturn($store);
        $this->design->method('getArea')->willReturn($area);
    }

    public function testDoesNotStopOuterEmulation(): void
    {
        // core already emulates the order's store in the frontend design: nothing to start, nothing to stop
        $this->currentStore(3, 'frontend');
        $this->emulation->expects($this->never())->method('startEnvironmentEmulation');
        $this->emulation->expects($this->never())->method('stopEnvironmentEmulation');

        $this->assertSame('x', $this->emulator->emulate(3, static fn (): string => 'x'));
    }

    public function testEmulatesAndStopsWhenStoreDiffers(): void
    {
        $this->currentStore(0, 'frontend');
        $this->emulation->expects($this->once())->method('startEnvironmentEmulation')->with(3, 'frontend', true);
        $this->emulation->expects($this->once())->method('stopEnvironmentEmulation');

        $this->assertSame('x', $this->emulator->emulate(3, static fn (): string => 'x'));
    }

    public function testEmulatesWhenAreaIsNotFrontend(): void
    {
        $this->currentStore(3, 'adminhtml');
        $this->emulation->expects($this->once())->method('startEnvironmentEmulation')->with(3, 'frontend', true);
        $this->emulation->expects($this->once())->method('stopEnvironmentEmulation');

        $this->emulator->emulate(3, static fn (): string => 'x');
    }

    public function testStopsEvenWhenTheCallbackThrows(): void
    {
        $this->currentStore(0, 'adminhtml');
        $this->emulation->expects($this->once())->method('stopEnvironmentEmulation');

        $this->expectException(\RuntimeException::class);
        $this->emulator->emulate(3, static function (): void {
            throw new \RuntimeException('boom');
        });
    }

    public function testStoreIdFromCoreIsComparedAsInteger(): void
    {
        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn('3'); // core hands the id out as a string
        $this->storeManager->method('getStore')->willReturn($store);
        $this->design->method('getArea')->willReturn('frontend');
        $this->emulation->expects($this->never())->method('startEnvironmentEmulation');

        $this->assertSame('x', $this->emulator->emulate(3, static fn (): string => 'x'));
    }
}
