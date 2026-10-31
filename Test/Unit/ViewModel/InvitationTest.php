<?php declare(strict_types=1);

namespace PixelPerfect\SalesEmail\Test\Unit\ViewModel;

use Magento\Cms\Api\Data\BlockInterface;
use Magento\Cms\Api\GetBlockByIdentifierInterface;
use Magento\Cms\Model\Template\Filter;
use Magento\Cms\Model\Template\FilterProvider;
use Magento\Framework\Exception\NoSuchEntityException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PixelPerfect\SalesEmail\Api\ConfigInterface;
use PixelPerfect\SalesEmail\Api\Service\EmailHtmlSanitizerInterface;
use PixelPerfect\SalesEmail\Api\Data\SanitizedHtmlInterfaceFactory;
use PixelPerfect\SalesEmail\ViewModel\Invitation;
use Psr\Log\LoggerInterface;

class InvitationTest extends TestCase
{
    private ConfigInterface&MockObject $config;
    private GetBlockByIdentifierInterface&MockObject $getBlock;
    private FilterProvider&MockObject $filterProvider;
    private EmailHtmlSanitizerInterface&MockObject $sanitizer;
    private LoggerInterface&MockObject $logger;
    private Invitation $viewModel;

    protected function setUp(): void
    {
        $this->config = $this->createMock(ConfigInterface::class);
        $this->getBlock = $this->createMock(GetBlockByIdentifierInterface::class);
        $this->filterProvider = $this->createMock(FilterProvider::class);
        $this->sanitizer = $this->createMock(EmailHtmlSanitizerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->viewModel = new Invitation(
            $this->config,
            $this->getBlock,
            $this->filterProvider,
            $this->sanitizer,
            $this->logger
        );
    }

    public function testEmptyWhenNotEnabledForType(): void
    {
        $this->config->method('isInvitationEnabledFor')->with('shipment', 3)->willReturn(false);
        $this->getBlock->expects($this->never())->method('execute');

        $this->assertSame('', $this->viewModel->getHtml('shipment', 3));
    }

    public function testEmptyWhenNoBlockConfigured(): void
    {
        $this->config->method('isInvitationEnabledFor')->willReturn(true);
        $this->config->method('getInvitationBlockId')->with(3)->willReturn(null);
        $this->getBlock->expects($this->never())->method('execute');

        $this->assertSame('', $this->viewModel->getHtml('order', 3));
    }

    public function testEmptyWhenBlockIsMissing(): void
    {
        $this->arrangeConfigured();
        $this->getBlock->method('execute')->willThrowException(new NoSuchEntityException(__('none')));

        $this->assertSame('', $this->viewModel->getHtml('order', 3));
    }

    public function testEmptyWhenBlockIsInactive(): void
    {
        $this->arrangeConfigured();
        $block = $this->createMock(BlockInterface::class);
        $block->method('isActive')->willReturn(false);
        $this->getBlock->method('execute')->willReturn($block);
        $this->filterProvider->expects($this->never())->method('getBlockFilter');

        $this->assertSame('', $this->viewModel->getHtml('order', 3));
    }

    public function testFiltersForStoreThenSanitizes(): void
    {
        $this->arrangeConfigured();
        $block = $this->createMock(BlockInterface::class);
        $block->method('isActive')->willReturn(true);
        $block->method('getContent')->willReturn('<p>{{store url=""}}</p>');
        $this->getBlock->expects($this->once())->method('execute')->with('invite', 3)->willReturn($block);

        $filter = $this->createMock(Filter::class);
        $filter->expects($this->once())->method('setStoreId')->with(3)->willReturnSelf();
        $filter->method('filter')->with('<p>{{store url=""}}</p>')->willReturn('<p>https://example.com/</p>');
        $this->filterProvider->method('getBlockFilter')->willReturn($filter);

        $this->sanitizer->expects($this->once())->method('sanitize')->with('<p>https://example.com/</p>')
            ->willReturn((new SanitizedHtmlInterfaceFactory())->create(['html' => '<p>clean</p>', 'removed' => []]));

        $this->assertSame('<p>clean</p>', $this->viewModel->getHtml('order', 3));
    }

    public function testFilterFailureIsLoggedAndYieldsEmpty(): void
    {
        $this->arrangeConfigured();
        $block = $this->createMock(BlockInterface::class);
        $block->method('isActive')->willReturn(true);
        $block->method('getContent')->willReturn('x');
        $this->getBlock->method('execute')->willReturn($block);
        $this->filterProvider->method('getBlockFilter')->willThrowException(new \RuntimeException('boom'));
        $this->logger->expects($this->once())->method('error');

        $this->assertSame('', $this->viewModel->getHtml('order', 3));
    }

    private function arrangeConfigured(): void
    {
        $this->config->method('isInvitationEnabledFor')->willReturn(true);
        $this->config->method('getInvitationBlockId')->willReturn('invite');
    }
}
