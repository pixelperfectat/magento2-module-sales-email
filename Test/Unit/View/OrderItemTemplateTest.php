<?php

declare(strict_types=1);

namespace PixelPerfect\SalesEmail\Test\Unit\View;

use Magento\Framework\Escaper;
use Magento\Framework\Translate\InlineInterface;
use Magento\Framework\ZendEscaper;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item as OrderItem;
use PHPUnit\Framework\TestCase;
use PixelPerfect\SalesEmail\ViewModel\EmailGiftMessage;
use PixelPerfect\SalesEmail\ViewModel\EmailItem;
use Psr\Log\LoggerInterface;

class OrderItemTemplateTest extends TestCase
{
    private const TEMPLATE = __DIR__ . '/../../../src/view/frontend/templates/email/items/order/default.phtml';

    /**
     * Include the template with the given block, the way the layout would
     */
    private function render(object $block): string
    {
        $escaper = new Escaper(null, $this->createMock(LoggerInterface::class));
        $inline = $this->createMock(InlineInterface::class);
        $inline->method('isAllowed')->willReturn(false);
        (new \ReflectionProperty(Escaper::class, 'translateInline'))->setValue($escaper, $inline);
        (new \ReflectionProperty(Escaper::class, 'escaper'))->setValue($escaper, new ZendEscaper());

        ob_start();
        (static function () use ($block, $escaper): void {
            include self::TEMPLATE;
        })();

        return (string)ob_get_clean();
    }

    private function block(OrderItem $item, ?object $infoBlock): object
    {
        $viewModel = $this->createMock(EmailItem::class);
        $viewModel->method('getImageUrl')->willReturn(null);
        $viewModel->method('formatQty')->willReturnCallback(static fn (float $qty): string => (string)$qty);
        $giftViewModel = $this->createMock(EmailGiftMessage::class);
        $giftViewModel->method('getItemHtml')->willReturn('');

        return new class ($item, $viewModel, $giftViewModel, $infoBlock) {
            public function __construct(
                private readonly OrderItem $item,
                private readonly EmailItem $viewModel,
                private readonly EmailGiftMessage $giftViewModel,
                private readonly ?object $infoBlock
            ) {
            }

            public function getItem(): OrderItem
            {
                return $this->item;
            }

            public function getData(string $key): mixed
            {
                return $key === 'view_model' ? $this->viewModel : $this->giftViewModel;
            }

            public function getItemOptions(): array
            {
                return [];
            }

            public function getItemPrice(OrderItem $item): string
            {
                return '<span class="price">19,99 EUR</span>';
            }

            public function getProductAdditionalInformationBlock(): ?object
            {
                return $this->infoBlock;
            }
        };
    }

    private function item(): OrderItem
    {
        $order = $this->createMock(Order::class);
        $order->method('getStoreId')->willReturn(1);
        $item = $this->createMock(OrderItem::class);
        $item->method('getOrder')->willReturn($order);
        $item->method('getName')->willReturn('Herbal Tea');
        $item->method('getQtyOrdered')->willReturn(2.0);

        return $item;
    }

    public function testRendersAdditionalProductInfoForTheItem(): void
    {
        $item = $this->item();
        $infoBlock = new class {
            public ?object $received = null;

            public function setItem(object $item): static
            {
                $this->received = $item;

                return $this;
            }

            public function toHtml(): string
            {
                return '<p class="pp-base-price">9,99 EUR / 1 kg</p>';
            }
        };

        $html = $this->render($this->block($item, $infoBlock));

        $this->assertStringContainsString('<p class="pp-base-price">9,99 EUR / 1 kg</p>', $html);
        $this->assertSame($item, $infoBlock->received);
        $this->assertStringContainsString(
            '<div class="pp-item-addinfo"><p class="pp-base-price">9,99 EUR / 1 kg</p></div>',
            preg_replace('/>\s+</', '><', $html)
        );
        $this->assertLessThan(
            strpos($html, 'pp-item-price'),
            strpos($html, 'pp-base-price'),
            'The extra line belongs in the info cell, before the price cell.'
        );
    }

    public function testRendersWithoutAdditionalProductInfoBlock(): void
    {
        $html = $this->render($this->block($this->item(), null));

        $this->assertStringContainsString('Herbal Tea', $html);
        $this->assertStringNotContainsString('pp-base-price', $html);
    }
}
