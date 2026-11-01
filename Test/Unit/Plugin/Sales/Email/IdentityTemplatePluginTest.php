<?php declare(strict_types=1);

namespace PixelPerfect\SalesEmail\Test\Unit\Plugin\Sales\Email;

use Magento\Sales\Model\Order\Email\Container\OrderIdentity;
use Magento\Sales\Model\Order\Email\Container\ShipmentIdentity;
use Magento\Store\Model\Store;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PixelPerfect\SalesEmail\Api\ConfigInterface;
use PixelPerfect\SalesEmail\Plugin\Sales\Email\IdentityTemplatePlugin;

class IdentityTemplatePluginTest extends TestCase
{
    private ConfigInterface&MockObject $config;
    private IdentityTemplatePlugin $plugin;

    protected function setUp(): void
    {
        $this->config = $this->createMock(ConfigInterface::class);
        $this->plugin = new IdentityTemplatePlugin($this->config);
    }

    public function testEnabledKeepsModuleTemplate(): void
    {
        $this->config->method('isEnabled')->with(5)->willReturn(true);

        $this->assertSame(
            'pp_sales_email_order_template',
            $this->plugin->afterGetTemplateId($this->identity(OrderIdentity::class, 5), 'pp_sales_email_order_template')
        );
    }

    public function testDisabledStoreFallsBackToCore(): void
    {
        $this->config->method('isEnabled')->with(5)->willReturn(false);
        $order = $this->identity(OrderIdentity::class, 5);
        $shipment = $this->identity(ShipmentIdentity::class, 5);

        $this->assertSame(
            'sales_email_order_template',
            $this->plugin->afterGetTemplateId($order, 'pp_sales_email_order_template')
        );
        $this->assertSame(
            'sales_email_order_guest_template',
            $this->plugin->afterGetGuestTemplateId($order, 'pp_sales_email_order_guest_template')
        );
        $this->assertSame(
            'sales_email_shipment_template',
            $this->plugin->afterGetTemplateId($shipment, 'pp_sales_email_shipment_template')
        );
        $this->assertSame(
            'sales_email_shipment_guest_template',
            $this->plugin->afterGetGuestTemplateId($shipment, 'pp_sales_email_shipment_guest_template')
        );
    }

    public function testOnlyTheDisabledStoreFallsBack(): void
    {
        $this->config->method('isEnabled')->willReturnMap([[1, true], [2, false]]);

        $this->assertSame(
            'pp_sales_email_order_template',
            $this->plugin->afterGetTemplateId($this->identity(OrderIdentity::class, 1), 'pp_sales_email_order_template')
        );
        $this->assertSame(
            'sales_email_order_template',
            $this->plugin->afterGetTemplateId($this->identity(OrderIdentity::class, 2), 'pp_sales_email_order_template')
        );
    }

    public function testAdminCustomTemplateIdUntouched(): void
    {
        $this->config->method('isEnabled')->willReturn(false);
        $order = $this->identity(OrderIdentity::class, 5);

        $this->assertSame('12', $this->plugin->afterGetTemplateId($order, '12'));
        $this->assertSame('7', $this->plugin->afterGetGuestTemplateId($order, '7'));
        $this->assertSame(
            'sales_email_order_template',
            $this->plugin->afterGetTemplateId($order, 'sales_email_order_template')
        );
    }

    public function testNonStringResultPassesThrough(): void
    {
        $this->config->method('isEnabled')->willReturn(false);

        $this->assertNull($this->plugin->afterGetTemplateId($this->identity(OrderIdentity::class, 5), null));
    }

    /**
     * @param class-string<OrderIdentity|ShipmentIdentity> $class
     * @param int $storeId
     * @return OrderIdentity|ShipmentIdentity&MockObject
     */
    private function identity(string $class, int $storeId): OrderIdentity|ShipmentIdentity
    {
        $store = $this->createMock(Store::class);
        $store->method('getId')->willReturn((string)$storeId); // core returns ids as strings
        $identity = $this->createMock($class);
        $identity->method('getStore')->willReturn($store);

        return $identity;
    }
}
