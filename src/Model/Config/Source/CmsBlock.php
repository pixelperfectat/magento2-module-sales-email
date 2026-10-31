<?php declare(strict_types=1);

namespace PixelPerfect\SalesEmail\Model\Config\Source;

use Magento\Cms\Model\ResourceModel\Block\CollectionFactory;
use Magento\Framework\Data\OptionSourceInterface;

class CmsBlock implements OptionSourceInterface
{
    /**
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(
        private readonly CollectionFactory $collectionFactory
    ) {
    }

    /**
     * Empty option (off) followed by all CMS blocks, valued by identifier
     *
     * @return array<int, array{value: string, label: string|\Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        $options = [['value' => '', 'label' => __('-- None --')]];
        $collection = $this->collectionFactory->create();
        $collection->setOrder('title', 'ASC');
        foreach ($collection as $block) {
            $options[] = [
                'value' => (string)$block->getData('identifier'),
                'label' => sprintf('%s (%s)', (string)$block->getData('title'), (string)$block->getData('identifier')),
            ];
        }

        return $options;
    }
}
