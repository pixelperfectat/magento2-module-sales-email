<?php

declare(strict_types=1);

namespace PixelPerfect\SalesEmail\Test\Unit\ViewModel;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Magento\Sales\Model\Order\Shipment;
use Magento\Sales\Model\Order\Shipment\Track;
use Magento\Shipping\Helper\Data as ShippingHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PixelPerfect\SalesEmail\Api\Data\TrackingInterfaceFactory;
use PixelPerfect\SalesEmail\ViewModel\ShipmentTracking;
use Psr\Log\LoggerInterface;

class ShipmentTrackingTest extends TestCase
{
    private ShipmentRepositoryInterface&MockObject $repository;
    private ShippingHelper&MockObject $helper;
    private ShipmentTracking $viewModel;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(ShipmentRepositoryInterface::class);
        $this->helper = $this->createMock(ShippingHelper::class);
        $this->viewModel = new ShipmentTracking(
            $this->repository,
            $this->helper,
            new TrackingInterfaceFactory(),
            $this->createMock(LoggerInterface::class)
        );
    }

    private function track(string $number, string $title): Track&MockObject
    {
        $track = $this->createMock(Track::class);
        $track->method('getTrackNumber')->willReturn($number);
        $track->method('getTitle')->willReturn($title);

        return $track;
    }

    /**
     * @param Track[] $tracks
     */
    private function shipmentWith(array $tracks): void
    {
        $shipment = $this->createMock(Shipment::class);
        $shipment->method('getAllTracks')->willReturn($tracks);
        $this->repository->method('get')->with(7)->willReturn($shipment);
    }

    public function testNoTracksGivesEmptyList(): void
    {
        $this->shipmentWith([]);

        $this->assertSame([], $this->viewModel->getTracks(7));
    }

    public function testOneTrackWithUrl(): void
    {
        $this->shipmentWith([$this->track('EX123456789AT', 'Example Parcel')]);
        $this->helper->method('getTrackingPopupUrlBySalesModel')
            ->willReturn('https://shop.example.com/shipping/tracking/popup?hash=abc');

        $tracks = $this->viewModel->getTracks(7);

        $this->assertCount(1, $tracks);
        $this->assertSame('EX123456789AT', $tracks[0]->getNumber());
        $this->assertSame('Example Parcel', $tracks[0]->getCarrierTitle());
        $this->assertSame('https://shop.example.com/shipping/tracking/popup?hash=abc', $tracks[0]->getUrl());
    }

    public function testEmptyUrlBecomesNull(): void
    {
        $this->shipmentWith([$this->track('EX1', 'Example Parcel')]);
        $this->helper->method('getTrackingPopupUrlBySalesModel')->willReturn('');

        $this->assertNull($this->viewModel->getTracks(7)[0]->getUrl());
    }

    public function testHelperFailureMeansNoUrlButKeepsTheTrack(): void
    {
        $this->shipmentWith([$this->track('EX1', 'Example Parcel')]);
        $this->helper->method('getTrackingPopupUrlBySalesModel')->willThrowException(new \RuntimeException('boom'));

        $tracks = $this->viewModel->getTracks(7);

        $this->assertCount(1, $tracks);
        $this->assertNull($tracks[0]->getUrl());
    }

    public function testTwoTracksKeepTheirOrder(): void
    {
        $this->shipmentWith([$this->track('EX1', 'Parcel A'), $this->track('EX2', 'Parcel B')]);
        $this->helper->method('getTrackingPopupUrlBySalesModel')->willReturn('https://shop.example.com/t');

        $tracks = $this->viewModel->getTracks(7);

        $this->assertSame(['EX1', 'EX2'], [$tracks[0]->getNumber(), $tracks[1]->getNumber()]);
    }

    public function testTrackWithoutNumberIsSkipped(): void
    {
        $this->shipmentWith([$this->track('  ', 'Parcel A'), $this->track('EX2', 'Parcel B')]);

        $tracks = $this->viewModel->getTracks(7);

        $this->assertCount(1, $tracks);
        $this->assertSame('EX2', $tracks[0]->getNumber());
    }

    public function testMissingShipmentGivesEmptyList(): void
    {
        $this->repository->method('get')->willThrowException(new NoSuchEntityException());

        $this->assertSame([], $this->viewModel->getTracks(7));
    }
}
