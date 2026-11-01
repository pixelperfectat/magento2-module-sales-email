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

    private function itemWithMessageId(mixed $id): OrderItem&MockObject
    {
        $item = $this->getMockBuilder(OrderItem::class)->disableOriginalConstructor()
            ->addMethods(['getGiftMessageId'])->getMock();
        $item->method('getGiftMessageId')->willReturn($id);

        return $item;
    }

    private function orderWithMessageId(mixed $id): Order&MockObject
    {
        $order = $this->getMockBuilder(Order::class)->disableOriginalConstructor()
            ->addMethods(['getGiftMessageId'])->onlyMethods(['getStore'])->getMock();
        $order->method('getGiftMessageId')->willReturn($id);

        return $order;
    }

    public function testExactMarkup(): void
    {
        $this->helper->method('getGiftMessage')->willReturn($this->message());

        $this->assertSame(
            '<table role="presentation" class="pp-gift" width="100%" cellpadding="0" cellspacing="0" border="0">'
            . '<tr><td class="pp-gift-cell" bgcolor="#f6f6f4"><strong>Gift Message</strong>'
            . '<br /><span class="pp-gift-label">From:</span> Jane &lt;b&gt;Doe&lt;/b&gt;'
            . '<br /><span class="pp-gift-label">To:</span> John Smith'
            . '<br /><span class="pp-gift-label">Message:</span> Happy days'
            . '</td></tr></table>',
            $this->viewModel->getItemHtml($this->itemWithMessageId(7))
        );
    }

    public function testMessageIdIsPassedAsInteger(): void
    {
        $this->helper->expects($this->once())->method('getGiftMessage')->with($this->identicalTo(7))
            ->willReturn($this->message());

        $this->viewModel->getItemHtml($this->itemWithMessageId('7'));
    }

    public function testOrderMessageIdIsPassedAsInteger(): void
    {
        $this->helper->method('isMessagesAllowed')->willReturn(true);
        $this->helper->expects($this->once())->method('getGiftMessage')->with($this->identicalTo(9))
            ->willReturn($this->message());

        $this->assertNotSame('', $this->viewModel->getOrderHtml($this->orderWithMessageId('9')));
    }

    /**
     * @dataProvider noMessageIdProvider
     */
    public function testItemWithoutMessageIdDoesNotLookUpAnything(mixed $id): void
    {
        $this->helper->expects($this->never())->method('getGiftMessage');

        $this->assertSame('', $this->viewModel->getItemHtml($this->itemWithMessageId($id)));
    }

    /**
     * @dataProvider noMessageIdProvider
     */
    public function testOrderWithoutMessageIdDoesNotLookUpAnything(mixed $id): void
    {
        $this->helper->expects($this->never())->method('isMessagesAllowed');
        $this->helper->expects($this->never())->method('getGiftMessage');

        $this->assertSame('', $this->viewModel->getOrderHtml($this->orderWithMessageId($id)));
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function noMessageIdProvider(): array
    {
        return ['null' => [null], 'zero' => [0], 'zero string' => ['0']];
    }

    public function testOrderMessageChecksTheOrdersStore(): void
    {
        $store = $this->createMock(\Magento\Store\Model\Store::class);
        $order = $this->orderWithMessageId(9);
        $order->method('getStore')->willReturn($store);
        $this->helper->expects($this->once())->method('isMessagesAllowed')->with('order', $order, $store)
            ->willReturn(false);

        $this->assertSame('', $this->viewModel->getOrderHtml($order));
    }

    public function testMissingMessageGivesEmptyString(): void
    {
        $this->helper->method('getGiftMessage')->willReturn(null);

        $this->assertSame('', $this->viewModel->getItemHtml($this->itemWithMessageId(7)));
    }

    public function testMessageWithoutTextGivesEmptyString(): void
    {
        $this->helper->method('getGiftMessage')
            ->willReturn(new DataObject(['sender' => 'Jane Doe', 'recipient' => 'John Smith', 'message' => '']));

        $this->assertSame('', $this->viewModel->getItemHtml($this->itemWithMessageId(7)));
    }

    public function testValuesAreEscapedAsStrings(): void
    {
        $seen = [];
        $escaper = $this->createMock(Escaper::class);
        $escaper->method('escapeHtml')->willReturnCallback(static function ($v) use (&$seen): string {
            $seen[] = $v;

            return htmlspecialchars((string)$v);
        });
        $viewModel = new EmailGiftMessage($this->helper, $escaper);
        $this->helper->method('getGiftMessage')
            ->willReturn(new DataObject(['sender' => null, 'recipient' => 42, 'message' => 7]));

        $html = $viewModel->getItemHtml($this->itemWithMessageId(7));

        $this->assertSame(['Gift Message', 'From:', '', 'To:', '42', 'Message:', '7'], $seen);
        $this->assertStringContainsString('<span class="pp-gift-label">To:</span> 42<br />', $html);
    }
}
