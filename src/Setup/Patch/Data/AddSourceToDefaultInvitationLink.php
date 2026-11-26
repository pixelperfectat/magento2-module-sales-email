<?php declare(strict_types=1);

namespace PixelPerfect\SalesEmail\Setup\Patch\Data;

use Magento\Cms\Api\BlockRepositoryInterface;
use Magento\Cms\Api\Data\BlockInterface;
use Magento\Cms\Model\ResourceModel\Block\CollectionFactory;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Gives the default invitation block of an existing install the link with the sign-up source.
 * Only a block whose content is still exactly the first default is updated; an edited block is left alone.
 */
class AddSourceToDefaultInvitationLink implements DataPatchInterface
{
    public const PREVIOUS_LINK = "{{store url=''}}#newsletter";
    public const LINK = "{{store url='' _query_nl_source=\$nl_source}}#newsletter";

    /**
     * @param BlockRepositoryInterface $blockRepository
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(
        private readonly BlockRepositoryInterface $blockRepository,
        private readonly CollectionFactory $collectionFactory
    ) {
    }

    /**
     * Content of the default block as the first release installed it
     *
     * @return string
     */
    public static function previousContent(): string
    {
        return str_replace(self::LINK, self::PREVIOUS_LINK, AddDefaultInvitationBlock::CONTENT);
    }

    /**
     * Replace the content of each unedited default block
     *
     * @return $this
     */
    public function apply(): self
    {
        $blocks = $this->collectionFactory->create()
            ->addFieldToFilter('identifier', AddDefaultInvitationBlock::IDENTIFIER)
            ->getItems();
        foreach ($blocks as $block) {
            if ($block instanceof BlockInterface && $block->getContent() === self::previousContent()) {
                $block->setContent(AddDefaultInvitationBlock::CONTENT);
                $this->blockRepository->save($block);
            }
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public static function getDependencies(): array
    {
        return [AddDefaultInvitationBlock::class];
    }

    /**
     * @inheritDoc
     */
    public function getAliases(): array
    {
        return [];
    }
}
