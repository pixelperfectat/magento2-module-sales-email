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
use PixelPerfect\SalesEmail\Api\Data\NewsletterStatus;
use PixelPerfect\SalesEmail\Api\Service\NewsletterStatusReaderInterface;
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
    private NewsletterStatusReaderInterface&MockObject $statusReader;
    private Invitation $viewModel;

    /** @var array<int, array<string, mixed>> variables handed to the block filter, per call */
    private array $filterVariables = [];

    /** @var array<int, array{0: string, 1: int}> email and store of every status question */
    private array $statusAsked = [];

    private NewsletterStatus $status = NewsletterStatus::Unknown;

    protected function setUp(): void
    {
        $this->config = $this->createMock(ConfigInterface::class);
        $this->getBlock = $this->createMock(GetBlockByIdentifierInterface::class);
        $this->filterProvider = $this->createMock(FilterProvider::class);
        $this->sanitizer = $this->createMock(EmailHtmlSanitizerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->statusReader = $this->createMock(NewsletterStatusReaderInterface::class);
        $this->statusReader->method('getStatus')->willReturnCallback(
            function (string $email, int $storeId): NewsletterStatus {
                $this->statusAsked[] = [$email, $storeId];

                return $this->status;
            }
        );
        $this->viewModel = new Invitation(
            $this->config,
            $this->getBlock,
            $this->filterProvider,
            $this->sanitizer,
            $this->logger,
            $this->statusReader
        );
    }

    public function testEmptyWhenNotEnabledForType(): void
    {
        $this->config->method('isInvitationEnabledFor')->with('shipment', 3)->willReturn(false);
        $this->config->expects($this->never())->method('getInvitationBlockId');
        $this->getBlock->expects($this->never())->method('execute');

        $this->assertSame('', $this->viewModel->getHtml('shipment', 3));
    }

    public function testEmptyWhenNoBlockConfigured(): void
    {
        $this->config->method('isInvitationEnabledFor')->willReturn(true);
        $this->config->method('getInvitationBlockId')->with(3)->willReturn(null);
        $this->getBlock->expects($this->never())->method('execute');
        $this->logger->expects($this->never())->method('error');

        $this->assertSame('', $this->viewModel->getHtml('order', 3));
    }

    public function testEmptyWhenBlockIsMissing(): void
    {
        $this->arrangeConfigured();
        $this->getBlock->method('execute')->willThrowException(new NoSuchEntityException(__('none')));
        $this->logger->expects($this->never())->method('error');

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
        $exception = new \RuntimeException('boom');
        $this->arrangeConfigured();
        $block = $this->createMock(BlockInterface::class);
        $block->method('isActive')->willReturn(true);
        $block->method('getContent')->willReturn('x');
        $this->getBlock->method('execute')->willReturn($block);
        $this->filterProvider->method('getBlockFilter')->willThrowException($exception);
        $this->logger->expects($this->once())->method('error')
            ->with(
                'Invitation block could not be rendered: boom',
                ['exception' => $exception]
            );

        $this->assertSame('', $this->viewModel->getHtml('order', 3));
    }

    public function testWithoutAStatusTheInvitationShowsAsBefore(): void
    {
        $this->arrangeStatusBlocks(NewsletterStatus::Unknown);

        $this->assertSame('<p>invite</p>', $this->viewModel->getHtml('order', 3, 'jane.doe@example.com'));
    }

    public function testARecipientWhoIsNotSubscribedIsInvited(): void
    {
        $this->arrangeStatusBlocks(NewsletterStatus::NotSubscribed);

        $this->assertSame('<p>invite</p>', $this->viewModel->getHtml('order', 3, 'jane.doe@example.com'));
    }

    public function testTheStatusIsAskedForTheRecipientInTheStoreOfTheEmail(): void
    {
        $this->arrangeStatusBlocks(NewsletterStatus::NotSubscribed);

        $this->viewModel->getHtml('shipment', 3, 'jane.doe@example.com');

        $this->assertSame([['jane.doe@example.com', 3]], $this->statusAsked);
    }

    public function testWithoutAnEmailTheStatusIsAskedForAnEmptyAddress(): void
    {
        $this->arrangeStatusBlocks(NewsletterStatus::Unknown);

        $this->assertSame('<p>invite</p>', $this->viewModel->getHtml('order', 3));
        $this->assertSame([['', 3]], $this->statusAsked);
    }

    public function testAPendingSubscriberGetsThePendingBlock(): void
    {
        $this->arrangeStatusBlocks(NewsletterStatus::Pending);

        $this->assertSame('<p>check your inbox</p>', $this->viewModel->getHtml('order', 3, 'jane.doe@example.com'));
    }

    public function testAConfirmedSubscriberGetsTheConfirmedBlock(): void
    {
        $this->arrangeStatusBlocks(NewsletterStatus::Confirmed);

        $this->assertSame('<p>thank you</p>', $this->viewModel->getHtml('order', 3, 'jane.doe@example.com'));
    }

    /**
     * @return array<string, array{0: NewsletterStatus}>
     */
    public function subscribedStatuses(): array
    {
        return ['pending' => [NewsletterStatus::Pending], 'confirmed' => [NewsletterStatus::Confirmed]];
    }

    /**
     * @dataProvider subscribedStatuses
     */
    public function testASubscriberGetsNothingWhenTheirBlockIsEmpty(NewsletterStatus $status): void
    {
        $this->arrangeStatusBlocks($status, pending: null, confirmed: null);
        $this->getBlock->expects($this->never())->method('execute');

        $this->assertSame('', $this->viewModel->getHtml('order', 3, 'jane.doe@example.com'));
    }

    public function testARecipientWhoUnsubscribedIsInvitedByDefault(): void
    {
        $this->arrangeStatusBlocks(NewsletterStatus::Unsubscribed);

        $this->assertSame('<p>invite</p>', $this->viewModel->getHtml('order', 3, 'jane.doe@example.com'));
    }

    public function testARecipientWhoUnsubscribedGetsNothingWhenHidden(): void
    {
        $this->arrangeStatusBlocks(NewsletterStatus::Unsubscribed, hideUnsubscribed: true);
        $this->getBlock->expects($this->never())->method('execute');

        $this->assertSame('', $this->viewModel->getHtml('order', 3, 'jane.doe@example.com'));
    }

    public function testHidingForUnsubscribedLeavesOtherRecipientsInvited(): void
    {
        $this->arrangeStatusBlocks(NewsletterStatus::NotSubscribed, hideUnsubscribed: true);

        $this->assertSame('<p>invite</p>', $this->viewModel->getHtml('order', 3, 'jane.doe@example.com'));
    }

    public function testNoStatusIsAskedWhenTheEmailTypeHasNoInvitation(): void
    {
        $this->config->method('isInvitationEnabledFor')->willReturn(false);

        $this->assertSame('', $this->viewModel->getHtml('order', 3, 'jane.doe@example.com'));
        $this->assertSame([], $this->statusAsked);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public function emailSources(): array
    {
        return ['order email' => ['order', 'order_email'], 'shipment email' => ['shipment', 'shipment_email']];
    }

    /**
     * @dataProvider emailSources
     */
    public function testTheBlockGetsTheSignUpSourceOfTheEmail(string $emailType, string $source): void
    {
        $this->arrangeStatusBlocks(NewsletterStatus::NotSubscribed);

        $this->viewModel->getHtml($emailType, 3, 'jane.doe@example.com');

        $this->assertSame([['nl_source' => $source]], $this->filterVariables);
    }

    public function testTheSourcePerEmailTypeCanBeReplaced(): void
    {
        $this->arrangeStatusBlocks(NewsletterStatus::NotSubscribed);
        $viewModel = new Invitation(
            $this->config,
            $this->getBlock,
            $this->filterProvider,
            $this->sanitizer,
            $this->logger,
            $this->statusReader,
            ['order' => 'landing_page']
        );

        $viewModel->getHtml('order', 3, 'jane.doe@example.com');
        $viewModel->getHtml('shipment', 3, 'jane.doe@example.com');

        $this->assertSame([['nl_source' => 'landing_page'], ['nl_source' => '']], $this->filterVariables);
    }

    /**
     * Configure all three blocks; each renders to its own text, filtered and sanitised like the invitation
     *
     * @param NewsletterStatus $status
     * @param string|null $pending
     * @param string|null $confirmed
     * @param bool $hideUnsubscribed
     */
    private function arrangeStatusBlocks(
        NewsletterStatus $status,
        ?string $pending = 'pending_block',
        ?string $confirmed = 'confirmed_block',
        bool $hideUnsubscribed = false
    ): void {
        $this->status = $status;
        $this->config->method('isInvitationEnabledFor')->willReturn(true);
        $this->config->method('getInvitationBlockId')->with(3)->willReturn('invite');
        $this->config->method('getPendingBlockId')->with(3)->willReturn($pending);
        $this->config->method('getConfirmedBlockId')->with(3)->willReturn($confirmed);
        $this->config->method('isHiddenForUnsubscribed')->with(3)->willReturn($hideUnsubscribed);
        $contents = ['invite' => 'invite', 'pending_block' => 'check your inbox', 'confirmed_block' => 'thank you'];
        $this->getBlock->method('execute')->willReturnCallback(
            function (string $identifier) use ($contents): BlockInterface {
                $block = $this->createMock(BlockInterface::class);
                $block->method('isActive')->willReturn(true);
                $block->method('getContent')->willReturn($contents[$identifier]);

                return $block;
            }
        );
        $filter = $this->createMock(Filter::class);
        $filter->method('setStoreId')->with(3)->willReturnSelf();
        $filter->method('setVariables')->willReturnCallback(function (array $variables) use ($filter): Filter {
            $this->filterVariables[] = $variables;

            return $filter;
        });
        $filter->method('filter')->willReturnCallback(static fn (string $content): string => '<p>' . $content . '</p>');
        $this->filterProvider->method('getBlockFilter')->willReturn($filter);
        $this->sanitizer->method('sanitize')->willReturnCallback(
            static fn (string $html) => (new SanitizedHtmlInterfaceFactory())->create(['html' => $html, 'removed' => []])
        );
    }

    private function arrangeConfigured(): void
    {
        $this->config->method('isInvitationEnabledFor')->willReturn(true);
        $this->config->method('getInvitationBlockId')->willReturn('invite');
    }
}
