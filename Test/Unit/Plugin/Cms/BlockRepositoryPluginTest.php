<?php declare(strict_types=1);

namespace PixelPerfect\SalesEmail\Test\Unit\Plugin\Cms;

use Magento\Cms\Api\BlockRepositoryInterface;
use Magento\Cms\Api\Data\BlockInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PixelPerfect\SalesEmail\Api\ConfigInterface;
use PixelPerfect\SalesEmail\Api\Data\SanitizedHtmlInterfaceFactory;
use PixelPerfect\SalesEmail\Plugin\Cms\BlockRepositoryPlugin;
use PixelPerfect\SalesEmail\Service\EmailHtmlSanitizer;
use Psr\Log\LoggerInterface;

class BlockRepositoryPluginTest extends TestCase
{
    private ConfigInterface&MockObject $config;
    private ManagerInterface&MockObject $messages;
    private StoreManagerInterface&MockObject $storeManager;
    private BlockRepositoryPlugin $plugin;

    protected function setUp(): void
    {
        $this->config = $this->createMock(ConfigInterface::class);
        $this->messages = $this->createMock(ManagerInterface::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $this->plugin = new BlockRepositoryPlugin(
            $this->config,
            new EmailHtmlSanitizer(new SanitizedHtmlInterfaceFactory(), $logger),
            $this->messages,
            $this->storeManager,
            $logger
        );
    }

    public function testWarnsWithRemovedTagsForConfiguredBlock(): void
    {
        $this->stores([0 => null, 1 => 'invite']);
        $block = $this->block('invite', '<p onclick="x()">a</p><script>1</script>');

        $this->messages->expects($this->once())->method('addWarningMessage')
            ->with($this->logicalAnd(
                $this->stringContains('script'),
                $this->stringContains('onclick attribute'),
                $this->stringContains('invite')
            ));

        $this->assertSame($block, $this->plugin->afterSave($this->createMock(BlockRepositoryInterface::class), $block, $block));
    }

    public function testNoWarningForOtherBlocks(): void
    {
        $this->stores([0 => null, 1 => 'invite']);
        $block = $this->block('other', '<script>1</script>');
        $this->messages->expects($this->never())->method('addWarningMessage');

        $this->plugin->afterSave($this->createMock(BlockRepositoryInterface::class), $block, $block);
    }

    public function testNoWarningWhenContentIsClean(): void
    {
        $this->stores([1 => 'invite']);
        $block = $this->block('invite', '<p>fine</p>');
        $this->messages->expects($this->never())->method('addWarningMessage');

        $this->plugin->afterSave($this->createMock(BlockRepositoryInterface::class), $block, $block);
    }

    public function testNeverThrowsAndStillReturnsResult(): void
    {
        $this->storeManager->method('getStores')->willThrowException(new \RuntimeException('boom'));
        $block = $this->block('invite', '<script>1</script>');

        $this->assertSame($block, $this->plugin->afterSave($this->createMock(BlockRepositoryInterface::class), $block, $block));
    }

    /**
     * @param array<int, string|null> $map store id => configured block identifier
     */
    private function stores(array $map): void
    {
        $stores = [];
        foreach (array_keys($map) as $id) {
            $store = $this->createMock(StoreInterface::class);
            $store->method('getId')->willReturn($id);
            $stores[] = $store;
        }
        $this->storeManager->method('getStores')->with(true)->willReturn($stores);
        $this->config->method('getInvitationBlockId')->willReturnCallback(
            static fn (?int $storeId): ?string => $map[$storeId] ?? null
        );
    }

    private function block(string $identifier, string $content): BlockInterface&MockObject
    {
        $block = $this->createMock(BlockInterface::class);
        $block->method('getIdentifier')->willReturn($identifier);
        $block->method('getContent')->willReturn($content);

        return $block;
    }
}
