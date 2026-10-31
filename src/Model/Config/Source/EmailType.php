<?php declare(strict_types=1);

namespace PixelPerfect\SalesEmail\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use PixelPerfect\SalesEmail\Api\ConfigInterface;

class EmailType implements OptionSourceInterface
{
    /**
     * @inheritDoc
     *
     * @return array<int, array{value: string, label: \Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => ConfigInterface::EMAIL_TYPE_ORDER, 'label' => __('Order confirmation')],
            ['value' => ConfigInterface::EMAIL_TYPE_SHIPMENT, 'label' => __('Shipment')],
        ];
    }
}
