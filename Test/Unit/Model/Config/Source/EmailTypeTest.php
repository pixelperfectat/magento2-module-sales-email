<?php declare(strict_types=1);

namespace PixelPerfect\SalesEmail\Test\Unit\Model\Config\Source;

use PHPUnit\Framework\TestCase;
use PixelPerfect\SalesEmail\Api\ConfigInterface;
use PixelPerfect\SalesEmail\Model\Config\Source\EmailType;

class EmailTypeTest extends TestCase
{
    public function testOffersOrderAndShipmentWithLabels(): void
    {
        $options = (new EmailType())->toOptionArray();

        $this->assertSame(
            [ConfigInterface::EMAIL_TYPE_ORDER, ConfigInterface::EMAIL_TYPE_SHIPMENT],
            array_column($options, 'value')
        );
        $this->assertSame(
            ['Order confirmation', 'Shipment'],
            array_map(static fn (array $o): string => (string)$o['label'], $options)
        );
    }
}
