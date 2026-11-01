<?php

declare(strict_types=1);

namespace PixelPerfect\SalesEmail\Test\Unit\View;

use Magento\Framework\DataObject;
use Magento\Framework\Escaper;
use Magento\Framework\Translate\InlineInterface;
use Magento\Framework\ZendEscaper;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\TestCase;
use PixelPerfect\SalesEmail\ViewModel\EmailTotals;
use Psr\Log\LoggerInterface;

class TotalsTemplateTest extends TestCase
{
    private const TEMPLATE = __DIR__ . '/../../../src/view/frontend/templates/email/totals.phtml';

    /**
     * @param array<string, DataObject> $totals
     */
    private function render(array $totals, ?string $iconUrl = null): string
    {
        $order = $this->createMock(Order::class);
        $order->method('getStoreId')->willReturn(1);
        $order->method('formatPrice')->willReturnCallback(
            static fn ($price): string => '<span class="price">' . ($price < 0 ? '-' : '') . '$'
                . number_format(abs((float)$price), 2) . '</span>'
        );
        $viewModel = $this->createMock(EmailTotals::class);
        $viewModel->method('getIconUrl')->willReturn($iconUrl);

        $block = new class ($totals, $order, $viewModel) {
            /**
             * @param array<string, DataObject> $totals
             */
            public function __construct(
                private readonly array $totals,
                private readonly Order $order,
                private readonly EmailTotals $viewModel
            ) {
            }

            public function getTotals(): array
            {
                return $this->totals;
            }

            public function getOrder(): Order
            {
                return $this->order;
            }

            public function getData(string $key): mixed
            {
                return $key === 'view_model' ? $this->viewModel : null;
            }

            public function formatValue(DataObject $total): string
            {
                return '<span class="price">' . ($total->getValue() < 0 ? '-' : '') . '$'
                    . number_format(abs((float)$total->getValue()), 2) . '</span>';
            }

            public function getChildHtml(string $name, bool $useCache = true): string
            {
                return '<tr class="child-' . $name . '"></tr>';
            }
        };
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

    private function row(string $code, string $label, float $value, array $extra = []): DataObject
    {
        return new DataObject(['code' => $code, 'label' => $label, 'value' => $value] + $extra);
    }

    public function testCoreRowsPrintLabelAndValue(): void
    {
        $html = $this->render([
            'subtotal' => $this->row('subtotal', 'Subtotal', 47.9),
            'grand_total' => $this->row('grand_total', 'Grand Total', 52.8, ['strong' => true]),
        ]);

        $this->assertStringContainsString('Subtotal', $html);
        $this->assertStringContainsString('$47.90', $html);
        $this->assertStringContainsString('pp-total-grand-label', $html);
        $this->assertStringContainsString('$52.80', $html);
    }

    public function testPriceMarkupIsNotEscapedInCoreAndDiscountLineRows(): void
    {
        $html = $this->render([
            'subtotal' => $this->row('subtotal', 'Subtotal', 10.0),
            'pp_discount_line_0' => $this->row('pp_discount_line_0', 'x', -2.0, [
                'discount_line' => ['kind' => 'coupon', 'code' => 'A', 'label' => 'A', 'amount' => 2.0],
            ]),
        ]);

        $this->assertStringContainsString('<span class="price">$10.00</span>', $html);
        $this->assertStringContainsString('<span class="price">-$2.00</span>', $html);
        $this->assertStringNotContainsString('&lt;span', $html);
    }

    public function testPlainDiscountRowKeepsCoreLabel(): void
    {
        $html = $this->render(['discount' => $this->row('discount', 'Discount (Summer)', -5.0)]);

        $this->assertStringContainsString('Discount (Summer)', $html);
        $this->assertStringContainsString('-$5.00', $html);
        $this->assertStringNotContainsString('monospace', $html);
    }

    public function testCouponLineShowsCodeInMonospace(): void
    {
        $html = $this->render(['pp_discount_line_0' => $this->row('pp_discount_line_0', 'Coupon SAVE15', -2.25, [
            'discount_line' => ['kind' => 'coupon', 'code' => 'SAVE15', 'label' => 'SAVE15', 'amount' => 2.25],
        ])]);

        $this->assertMatchesRegularExpression(
            '~<span class="pp-code" style="font-family:Menlo,Consolas,monospace">SAVE15</span>~',
            $html
        );
        $this->assertStringContainsString('Coupon', $html);
        $this->assertStringContainsString('-$2.25', $html);
    }

    public function testZeroGiftPrintsGiftInsteadOfAmount(): void
    {
        $html = $this->render(['pp_discount_line_0' => $this->row('pp_discount_line_0', 'Gift: FREEBIE', 0.0, [
            'discount_line' => ['kind' => 'gift', 'code' => 'FREEBIE', 'label' => 'FREEBIE', 'amount' => 0.0],
        ])]);

        $this->assertMatchesRegularExpression('~<td class="pp-total-value" align="right">Gift</td>~', $html);
        $this->assertStringNotContainsString('$0.00', $html);
    }

    public function testZeroFreeShippingPrintsFree(): void
    {
        $html = $this->render(['pp_discount_line_0' => $this->row('pp_discount_line_0', 'Free shipping', 0.0, [
            'discount_line' => ['kind' => 'free_shipping', 'code' => null, 'label' => 'Free shipping', 'amount' => 0.0],
        ])]);

        $this->assertMatchesRegularExpression('~<td class="pp-total-value" align="right">Free</td>~', $html);
        $this->assertStringContainsString('Free shipping', $html);
    }

    public function testGiftWithMoneyPrintsNegativeAmount(): void
    {
        $html = $this->render(['pp_discount_line_0' => $this->row('pp_discount_line_0', 'Gift: TEA', -3.5, [
            'discount_line' => ['kind' => 'gift', 'code' => 'TEA', 'label' => 'TEA', 'amount' => 3.5],
        ])]);

        $this->assertMatchesRegularExpression('~<td class="pp-total-value" align="right"><span class="price">-\$3\.50</span></td>~', $html);
    }

    public function testGiftCardRowWithDiscountLineKeyRendersAsDiscountLine(): void
    {
        $html = $this->render(['gift_card' => $this->row('gift_card', 'Gift Card (ABCD-****)', -10.0, [
            'discount_line' => ['kind' => 'gift_card', 'code' => 'ABCD-****', 'label' => 'ABCD-****', 'amount' => 10.0],
        ])]);

        $this->assertStringContainsString('Gift card', $html);
        $this->assertStringContainsString('>ABCD-****</span>', $html);
        $this->assertStringContainsString('-$10.00', $html);
    }

    public function testEverythingIsEscaped(): void
    {
        $html = $this->render(['pp_discount_line_0' => $this->row('pp_discount_line_0', 'x', -1.0, [
            'discount_line' => ['kind' => 'coupon', 'code' => '<script>alert(1)</script>', 'label' => 'x', 'amount' => 1.0],
        ])]);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testIconOnlyWhenViewModelReturnsUrl(): void
    {
        $row = ['pp_discount_line_0' => $this->row('pp_discount_line_0', 'x', -1.0, [
            'discount_line' => ['kind' => 'coupon', 'code' => 'A', 'label' => 'A', 'amount' => 1.0],
        ])];

        $this->assertStringNotContainsString('<img', $this->render($row));
        $with = $this->render($row, 'https://shop.example.com/static/coupon.png');
        $this->assertStringContainsString('<img class="pp-total-icon" src="https://shop.example.com/static/coupon.png"', $with);
    }

    public function testBlockNamedRowsRenderTheChildBlock(): void
    {
        $html = $this->render(['tax' => new DataObject(['code' => 'tax', 'block_name' => 'tax'])]);

        $this->assertStringContainsString('child-tax', $html);
    }
}
