<?php declare(strict_types=1);

namespace PixelPerfect\SalesEmail\Test\Unit\Service;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PixelPerfect\SalesEmail\Api\ConfigInterface;
use PixelPerfect\SalesEmail\Service\Config;

class ConfigTest extends TestCase
{
    private ScopeConfigInterface&MockObject $scopeConfig;
    private Config $config;

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->config = new Config($this->scopeConfig);
    }

    public function testIsEnabledReadsStoreScope(): void
    {
        $this->scopeConfig->expects($this->once())
            ->method('isSetFlag')
            ->with(ConfigInterface::XML_PATH_ENABLED, ScopeInterface::SCOPE_STORE, 4)
            ->willReturn(true);

        $this->assertTrue($this->config->isEnabled(4));
    }

    public function testEmptyBlockIsNull(): void
    {
        $this->scopeConfig->method('getValue')->willReturn('');

        $this->assertNull($this->config->getInvitationBlockId());
    }

    public function testUnsetBlockIsNull(): void
    {
        $this->scopeConfig->method('getValue')->willReturn(null);

        $this->assertNull($this->config->getInvitationBlockId());
    }

    public function testBlockIdentifierIsReturned(): void
    {
        $this->scopeConfig->method('getValue')
            ->with(ConfigInterface::XML_PATH_CMS_BLOCK, ScopeInterface::SCOPE_STORE, 2)
            ->willReturn('pp_sales_email_invitation');

        $this->assertSame('pp_sales_email_invitation', $this->config->getInvitationBlockId(2));
    }

    public function testMultiselectDefaultCoversOrderAndShipment(): void
    {
        $this->scopeConfig->method('getValue')->willReturn('order,shipment');

        $this->assertTrue($this->config->isInvitationEnabledFor('order'));
        $this->assertTrue($this->config->isInvitationEnabledFor('shipment'));
    }

    public function testMultiselectWithOneTypeExcludesTheOther(): void
    {
        $this->scopeConfig->method('getValue')->willReturn('shipment');

        $this->assertFalse($this->config->isInvitationEnabledFor('order'));
        $this->assertTrue($this->config->isInvitationEnabledFor('shipment'));
    }

    public function testMultiselectToleratesWhitespace(): void
    {
        $this->scopeConfig->method('getValue')->willReturn('order, shipment');

        $this->assertTrue($this->config->isInvitationEnabledFor('shipment'));
    }

    public function testEmptyMultiselectEnablesNothing(): void
    {
        $this->scopeConfig->method('getValue')->willReturn('');

        $this->assertFalse($this->config->isInvitationEnabledFor('order'));
        $this->assertFalse($this->config->isInvitationEnabledFor('shipment'));
    }

    public function testNullMultiselectEnablesNothing(): void
    {
        $this->scopeConfig->method('getValue')->willReturn(null);

        $this->assertFalse($this->config->isInvitationEnabledFor('order'));
    }

    public function testMultiselectReadsStoreScope(): void
    {
        $this->scopeConfig->expects($this->once())
            ->method('getValue')
            ->with(ConfigInterface::XML_PATH_ENABLED_FOR, ScopeInterface::SCOPE_STORE, 9)
            ->willReturn('order');

        $this->assertTrue($this->config->isInvitationEnabledFor('order', 9));
    }
}
