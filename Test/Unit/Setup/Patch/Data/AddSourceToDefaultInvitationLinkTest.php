<?php declare(strict_types=1);

namespace PixelPerfect\SalesEmail\Test\Unit\Setup\Patch\Data;

use Magento\Cms\Api\BlockRepositoryInterface;
use Magento\Cms\Api\Data\BlockInterface;
use Magento\Cms\Model\ResourceModel\Block\Collection;
use Magento\Cms\Model\ResourceModel\Block\CollectionFactory;
use Magento\Framework\DataObject;
use PHPUnit\Framework\TestCase;
use PixelPerfect\SalesEmail\Setup\Patch\Data\AddDefaultInvitationBlock;
use PixelPerfect\SalesEmail\Setup\Patch\Data\AddSourceToDefaultInvitationLink;

class AddSourceToDefaultInvitationLinkTest extends TestCase
{
    /** @var array<int, string> identifiers of the saved blocks */
    private array $saved = [];

    public function testTheNewDefaultLinkCarriesTheSourceVariable(): void
    {
        $this->assertStringContainsString(
            'href="' . AddSourceToDefaultInvitationLink::LINK . '"',
            AddDefaultInvitationBlock::CONTENT
        );
        $this->assertSame(
            "{{store url='' _query_nl_source=\$nl_source}}#newsletter",
            AddSourceToDefaultInvitationLink::LINK
        );
    }

    public function testThePreviousContentDiffersOnlyInTheLink(): void
    {
        $previous = AddSourceToDefaultInvitationLink::previousContent();

        $this->assertStringContainsString("href=\"{{store url=''}}#newsletter\"", $previous);
        $this->assertStringNotContainsString('nl_source', $previous);
        $this->assertSame(
            AddDefaultInvitationBlock::CONTENT,
            str_replace("{{store url=''}}#newsletter", AddSourceToDefaultInvitationLink::LINK, $previous)
        );
    }

    public function testAnUneditedDefaultBlockGetsTheNewContent(): void
    {
        $block = $this->block(7, AddSourceToDefaultInvitationLink::previousContent());

        $this->patch([$block])->apply();

        $this->assertSame(AddDefaultInvitationBlock::CONTENT, $block->getContent());
        $this->assertSame([7], $this->saved);
    }

    public function testAnEditedBlockIsLeftAlone(): void
    {
        $block = $this->block(7, '<p>Our own invitation</p>');

        $this->patch([$block])->apply();

        $this->assertSame('<p>Our own invitation</p>', $block->getContent());
        $this->assertSame([], $this->saved);
    }

    public function testEachUneditedBlockWithTheIdentifierIsUpdated(): void
    {
        $blocks = [
            $this->block(7, AddSourceToDefaultInvitationLink::previousContent()),
            $this->block(8, '<p>edited</p>'),
            $this->block(9, AddSourceToDefaultInvitationLink::previousContent()),
        ];

        $this->patch($blocks)->apply();

        $this->assertSame([7, 9], $this->saved);
    }

    public function testItRunsAfterTheDefaultBlockPatch(): void
    {
        $this->assertSame([AddDefaultInvitationBlock::class], AddSourceToDefaultInvitationLink::getDependencies());
        $this->assertSame([], $this->patch([])->getAliases());
    }

    /**
     * @param array<int, BlockInterface|DataObject> $blocks
     */
    private function patch(array $blocks): AddSourceToDefaultInvitationLink
    {
        $collection = $this->createMock(Collection::class);
        $collection->method('addFieldToFilter')
            ->with('identifier', 'pp_sales_email_invitation')
            ->willReturnSelf();
        $collection->method('getItems')->willReturn($blocks);
        $factory = $this->createMock(CollectionFactory::class);
        $factory->method('create')->willReturn($collection);
        $repository = $this->createMock(BlockRepositoryInterface::class);
        $repository->method('save')->willReturnCallback(function (BlockInterface $block): BlockInterface {
            $this->saved[] = (int)$block->getId();

            return $block;
        });

        return new AddSourceToDefaultInvitationLink($repository, $factory);
    }

    private function block(int $id, string $content): BlockInterface
    {
        return new class ($id, $content) extends DataObject implements BlockInterface {
            public function __construct(int $id, string $content)
            {
                parent::__construct(['block_id' => $id, 'content' => $content]);
            }

            // phpcs:disable
            public function getId() { return $this->getData('block_id'); }
            public function getIdentifier() { return 'pp_sales_email_invitation'; }
            public function getTitle() { return null; }
            public function getContent() { return $this->getData('content'); }
            public function getCreationTime() { return null; }
            public function getUpdateTime() { return null; }
            public function isActive() { return true; }
            public function setId($id) { return $this->setData('block_id', $id); }
            public function setIdentifier($identifier) { return $this; }
            public function setTitle($title) { return $this; }
            public function setContent($content) { return $this->setData('content', $content); }
            public function setCreationTime($creationTime) { return $this; }
            public function setUpdateTime($updateTime) { return $this; }
            public function setIsActive($isActive) { return $this; }
            // phpcs:enable
        };
    }
}
