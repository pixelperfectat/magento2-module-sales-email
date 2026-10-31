<?php

declare(strict_types=1);

namespace PixelPerfect\SalesEmail\Test\Unit\ViewModel;

use Magento\Framework\DataObject;
use Magento\Framework\Escaper;
use Magento\GiftMessage\Helper\Message as GiftMessageHelper;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item as OrderItem;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PixelPerfect\SalesEmail\ViewModel\EmailGiftMessage;

class EmailGiftMessageTest extends TestCase
{
    private GiftMessageHelper&MockObject $helper;
    private EmailGiftMessage $viewModel;

    protected function setUp(): void
    {
        $this->helper = $this->createMock(GiftMessageHelper::class);
        $escaper = $this->createMock(Escaper::class);
        $escaper->method('escapeHtml')->willReturnCallback(static fn ($v): string => htmlspecialchars((string)$v));
        $this->viewModel = new EmailGiftMessage($this->helper, $escaper);
    }

    private function message(): DataObject
    {
        return new DataObject(['sender' => 'Jane <b>Doe</b>', 'recipient' => 'John Smith', 'message' => 'Happy days']);
    }

    public function testItemWithoutMessageIsEmpty(): void
    {
        $item = $this->getMockBuilder(OrderItem::class)->disableOriginalConstructor()
            ->addMethods(['getGiftMessageId'])->getMock();
        $item->method('getGiftMessageId')->willReturn(null);

        $this->assertSame('', $this->viewModel->getItemHtml($item));
    }

    public function testItemMessageIsRenderedAndEscaped(): void
    {
        $item = $this->getMockBuilder(OrderItem::class)->disableOriginalConstructor()
            ->addMethods(['getGiftMessageId'])->getMock();
        $item->method('getGiftMessageId')->willReturn(7);
        $this->helper->method('getGiftMessage')->with(7)->willReturn($this->message());

        $html = $this->viewModel->getItemHtml($item);

        $this->assertStringContainsString('Happy days', $html);
        $this->assertStringContainsString('John Smith', $html);
        $this->assertStringContainsString('Jane &lt;b&gt;Doe&lt;/b&gt;', $html);
        $this->assertStringNotContainsString('<b>', $html);
    }

    public function testOrderMessageOnlyWhenAllowed(): void
    {
        $order = $this->getMockBuilder(Order::class)->disableOriginalConstructor()
            ->addMethods(['getGiftMessageId'])->onlyMethods(['getStore'])->getMock();
        $order->method('getGiftMessageId')->willReturn(9);
        $this->helper->method('getGiftMessage')->willReturn($this->message());
        $this->helper->method('isMessagesAllowed')->willReturn(false);

        $this->assertSame('', $this->viewModel->getOrderHtml($order));
    }

    public function testOrderMessageIsRenderedWhenAllowed(): void
    {
        $order = $this->getMockBuilder(Order::class)->disableOriginalConstructor()
            ->addMethods(['getGiftMessageId'])->onlyMethods(['getStore'])->getMock();
        $order->method('getGiftMessageId')->willReturn(9);
        $this->helper->method('getGiftMessage')->with(9)->willReturn($this->message());
        $this->helper->method('isMessagesAllowed')->willReturn(true);

        $this->assertStringContainsString('Happy days', $this->viewModel->getOrderHtml($order));
    }
}
