<?php

declare(strict_types=1);

namespace PixelPerfect\SalesEmail\Test\Unit\View;

use PHPUnit\Framework\TestCase;

class ShipmentEmailFilesTest extends TestCase
{
    private const EMAIL = __DIR__ . '/../../../src/view/frontend/email';

    /**
     * @return array<string, array{string}>
     */
    public static function templates(): array
    {
        return ['customer' => ['shipment_new.html'], 'guest' => ['shipment_new_guest.html']];
    }

    /**
     * @dataProvider templates
     */
    public function testShipmentTemplateIsTheModernLayout(string $file): void
    {
        $html = (string)file_get_contents(self::EMAIL . '/' . $file);

        $this->assertStringContainsString('pp_sales_email_shipment_tracking', $html);
        $this->assertStringContainsString('pp_sales_email_shipment_items', $html);
        $this->assertStringContainsString('formattedShippingAddress', $html);
        $this->assertStringNotContainsString('formattedBillingAddress', $html);
        $this->assertStringNotContainsString('payment_html', $html);
        $this->assertStringNotContainsString('design/email/header_template', $html);
        $this->assertStringContainsString('{{var comment|escape|nl2br}}', $html);
        $this->assertStringContainsString('Your %store_name package is on its way', $html);
    }

    public function testOnlyTheCustomerVariantLinksToTheAccount(): void
    {
        $this->assertStringContainsString('customer/account/', (string)file_get_contents(self::EMAIL . '/shipment_new.html'));
        $this->assertStringNotContainsString('<a href="%account_url">', (string)file_get_contents(self::EMAIL . '/shipment_new_guest.html'));
    }
}
