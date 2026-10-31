<?php

declare(strict_types=1);

namespace PixelPerfect\SalesEmail\Test\Unit\View;

use Magento\Framework\DataObject;
use Magento\Framework\Escaper;
use Magento\Framework\Translate\InlineInterface;
use Magento\Framework\ZendEscaper;
use Magento\Sales\Model\Order\Item as OrderItem;
use PHPUnit\Framework\TestCase;
use PixelPerfect\SalesEmail\ViewModel\EmailItem;
use PixelPerfect\SalesEmail\ViewModel\Invitation;
use Psr\Log\LoggerInterface;

class ShipmentItemTemplateTest extends TestCase
{
    private const TEMPLATES = __DIR__ . '/../../../src/view/frontend/templates/email';

    /**
     * Include a template with the given block, the way the layout would
     *
     * The real Escaper resolves its collaborators through the ObjectManager; plain ones are injected by reflection
     * so the template runs unchanged without a Magento bootstrap.
     */
    private function render(string $template, object $block): string
    {
        $escaper = new Escaper(null, $this->createMock(LoggerInterface::class));
        $inline = $this->createMock(InlineInterface::class);
        $inline->method('isAllowed')->willReturn(false);
        (new \ReflectionProperty(Escaper::class, 'translateInline'))->setValue($escaper, $inline);
        (new \ReflectionProperty(Escaper::class, 'escaper'))->setValue($escaper, new ZendEscaper());

        ob_start();
        (static function () use ($block, $escaper, $template): void {
            include self::TEMPLATES . '/' . $template;
        })();

        return (string)ob_get_clean();
    }

    private function viewModel(): EmailItem
    {
        $viewModel = $this->createMock(EmailItem::class);
        $viewModel->method('getImageUrl')->willReturn(null);
        $viewModel->method('formatQty')->willReturnCallback(static fn (float $qty): string => (string)$qty);

        return $viewModel;
    }

    /**
     * @param DataObject[] $children
     */
    private function orderItem(array $children = []): OrderItem
    {
        $orderItem = $this->createMock(OrderItem::class);
        $orderItem->method('getChildrenItems')->willReturn($children);

        return $orderItem;
    }

    private function shipmentItem(string $name, float $qty, int $id = 1): DataObject
    {
        return new DataObject([
            'id' => $id,
            'name' => $name,
            'qty' => $qty,
            'order' => new DataObject(['store_id' => 1]),
            'order_item' => $this->orderItem(),
        ]);
    }

    public function testDefaultRowShowsShippedQuantityNotOrdered(): void
    {
        $item = $this->shipmentItem('Herbal Tea', 1.0);
        $block = new class ($item, $this->viewModel()) {
            public function __construct(private readonly DataObject $item, private readonly EmailItem $vm)
            {
            }

            public function getItem(): DataObject
            {
                return $this->item;
            }

            public function getData(string $key): mixed
            {
                return $this->vm;
            }

            public function getItemOptions(): array
            {
                return [];
            }
        };

        $html = $this->render('items/shipment/default.phtml', $block);

        $this->assertStringContainsString('Qty: 1', $html);
        $this->assertStringNotContainsString('Qty: 3', $html);
    }

    public function testItemNameIsEscaped(): void
    {
        $block = new class ($this->shipmentItem('<script>x</script> Tea', 1.0), $this->viewModel()) {
            public function __construct(private readonly DataObject $item, private readonly EmailItem $vm)
            {
            }

            public function getItem(): DataObject
            {
                return $this->item;
            }

            public function getData(string $key): mixed
            {
                return $this->vm;
            }

            public function getItemOptions(): array
            {
                return [];
            }
        };

        $html = $this->render('items/shipment/default.phtml', $block);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;x&lt;/script&gt; Tea', $html);
    }

    /**
     * @param DataObject[] $shipped shipment items keyed by order item id
     */
    private function bundleBlock(bool $separately, array $shipped): object
    {
        $parent = $this->shipmentItem('Gift Set', 2.0, 10);
        $children = [];
        foreach ([11 => 'Tea Part', 12 => 'Soap Part'] as $id => $name) {
            $children[] = new DataObject(['id' => $id, 'name' => $name]);
        }
        $parent->setData('order_item', $this->orderItem($children));

        return new class ($parent, $this->viewModel(), $separately, $shipped) {
            /**
             * @param DataObject[] $shipped
             */
            public function __construct(
                private readonly DataObject $parent,
                private readonly EmailItem $vm,
                private readonly bool $separately,
                private readonly array $shipped
            ) {
            }

            public function getItem(): DataObject
            {
                return $this->parent;
            }

            public function getData(string $key): mixed
            {
                return $this->vm;
            }

            public function getChildren(DataObject $parent): array
            {
                return $this->shipped;
            }

            public function isShipmentSeparately(): bool
            {
                return $this->separately;
            }

            public function getItemOptions(): array
            {
                return [];
            }

            public function getSelectionAttributes(DataObject $child): array
            {
                return ['option_id' => 1, 'option_label' => 'Part'];
            }

            public function getValueHtml(DataObject $child): string
            {
                return '<span>' . $child->getName() . '</span>';
            }
        };
    }

    public function testBundleShippedTogetherShowsParentQuantityOnly(): void
    {
        $html = $this->render('items/shipment/bundle.phtml', $this->bundleBlock(false, []));

        $this->assertSame(1, substr_count($html, 'Qty:'));
        $this->assertStringContainsString('Qty: 2', $html);
        $this->assertStringContainsString('Tea Part', $html);
        $this->assertStringContainsString('Soap Part', $html);
    }

    public function testBundleShippedSeparatelyListsOnlyShippedSelections(): void
    {
        $shipped = [11 => $this->shipmentItem('Tea Part', 3.0, 11)];

        $html = $this->render('items/shipment/bundle.phtml', $this->bundleBlock(true, $shipped));

        $this->assertStringContainsString('Tea Part', $html);
        $this->assertStringNotContainsString('Soap Part', $html);
        $this->assertSame(1, substr_count($html, 'Qty:'));
        $this->assertStringContainsString('Qty: 3', $html);
    }

    private function itemsBlock(string $invitationHtml): object
    {
        $invitation = $this->createMock(Invitation::class);
        $invitation->expects($this->once())->method('getHtml')->with('shipment', 1)->willReturn($invitationHtml);
        $shipment = new DataObject(['all_items' => []]);
        $order = new DataObject(['store_id' => 1]);

        return new class ($shipment, $order, $invitation) {
            public function __construct(
                private readonly DataObject $shipment,
                private readonly DataObject $order,
                private readonly Invitation $invitation
            ) {
            }

            public function getShipment(): DataObject
            {
                return $this->shipment;
            }

            public function getOrder(): DataObject
            {
                return $this->order;
            }

            public function getData(string $key): mixed
            {
                return $this->invitation;
            }

            public function getItemHtml(DataObject $item): string
            {
                return '';
            }
        };
    }

    public function testInvitationRowIsAbsentWhenEmpty(): void
    {
        $html = $this->render('shipment/items.phtml', $this->itemsBlock(''));

        $this->assertStringContainsString('In this package', $html);
        $this->assertStringNotContainsString('pp-invitation', $html);
    }

    public function testInvitationRowIsPresentWhenGiven(): void
    {
        $html = $this->render('shipment/items.phtml', $this->itemsBlock('<p>Join us</p>'));

        $this->assertStringContainsString('class="pp-invitation"', $html);
        $this->assertStringContainsString('<p>Join us</p>', $html);
    }
}
