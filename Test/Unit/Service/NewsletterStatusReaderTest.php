<?php declare(strict_types=1);

namespace PixelPerfect\SalesEmail\Test\Unit\Service;

use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PixelPerfect\SalesEmail\Api\Data\NewsletterStatus;
use PixelPerfect\SalesEmail\Service\NewsletterStatusReader;
use PixelPerfect\SalesEmail\Test\Stub\SubscriptionStatusStub;
use Psr\Log\LoggerInterface;

class NewsletterStatusReaderTest extends TestCase
{
    private const STATUS_CHECK = 'PixelPerfect\NewsletterSignup\Api\Service\SubscriptionStatusCheckInterface';

    private ModuleManager&MockObject $moduleManager;
    private ObjectManagerInterface&MockObject $objectManager;
    private StoreManagerInterface&MockObject $storeManager;
    private LoggerInterface&MockObject $logger;

    /** @var array<int, array{0: string, 1: int}> email and website of every status check call */
    private array $asked = [];

    private ?\Throwable $checkFailure = null;

    private SubscriptionStatusStub $answer = SubscriptionStatusStub::NotSubscribed;

    protected function setUp(): void
    {
        $this->moduleManager = $this->createMock(ModuleManager::class);
        $this->moduleManager->method('isEnabled')->willReturnCallback(
            static fn (string $module): bool => $module === 'PixelPerfect_NewsletterSignup'
        );
        $this->objectManager = $this->createMock(ObjectManagerInterface::class);
        $this->objectManager->method('get')->willReturnCallback(fn (string $type): object => $this->statusCheck($type));
        $store = $this->createMock(StoreInterface::class);
        $store->method('getWebsiteId')->willReturn('2');
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->storeManager->method('getStore')->with(5)->willReturn($store);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    /**
     * @return array<string, array{0: SubscriptionStatusStub, 1: NewsletterStatus}>
     */
    public function statuses(): array
    {
        return [
            'not subscribed' => [SubscriptionStatusStub::NotSubscribed, NewsletterStatus::NotSubscribed],
            'pending' => [SubscriptionStatusStub::Pending, NewsletterStatus::Pending],
            'confirmed' => [SubscriptionStatusStub::Confirmed, NewsletterStatus::Confirmed],
            'unsubscribed' => [SubscriptionStatusStub::Unsubscribed, NewsletterStatus::Unsubscribed],
            'a status this module does not know' => [SubscriptionStatusStub::SomethingNew, NewsletterStatus::Unknown],
        ];
    }

    /**
     * @dataProvider statuses
     */
    public function testTheStatusOfTheNewsletterModuleIsPassedOn(
        SubscriptionStatusStub $answer,
        NewsletterStatus $expected
    ): void {
        $this->answer = $answer;

        $this->assertSame($expected, $this->reader()->getStatus('jane.doe@example.com', 5));
    }

    public function testTheEmailIsAskedForOnTheWebsiteOfTheStore(): void
    {
        $this->reader()->getStatus('jane.doe@example.com', 5);

        $this->assertSame([['jane.doe@example.com', 2]], $this->asked);
    }

    public function testWithoutTheNewsletterModuleTheStatusIsUnknownAndNothingIsAsked(): void
    {
        $moduleManager = $this->createMock(ModuleManager::class);
        $moduleManager->method('isEnabled')->willReturn(false);
        $this->objectManager->expects($this->never())->method('get');

        $reader = new NewsletterStatusReader($moduleManager, $this->objectManager, $this->storeManager, $this->logger);

        $this->assertSame(NewsletterStatus::Unknown, $reader->getStatus('jane.doe@example.com', 5));
    }

    public function testAnEmptyEmailIsUnknownAndNothingIsAsked(): void
    {
        $this->objectManager->expects($this->never())->method('get');

        $this->assertSame(NewsletterStatus::Unknown, $this->reader()->getStatus(" \n", 5));
    }

    public function testAFailingStatusCheckIsLoggedAndUnknown(): void
    {
        $this->checkFailure = new \RuntimeException('database gone');
        $this->logger->expects($this->once())->method('error')->with(
            'Newsletter status could not be read: database gone',
            ['exception' => $this->checkFailure]
        );

        $this->assertSame(NewsletterStatus::Unknown, $this->reader()->getStatus('jane.doe@example.com', 5));
    }

    public function testAStatusCheckReturningNoEnumIsUnknown(): void
    {
        $objectManager = $this->createMock(ObjectManagerInterface::class);
        $objectManager->method('get')->willReturn(new class {
            public function getStatus(string $email, int $websiteId): string
            {
                return 'Confirmed';
            }
        });
        $reader = new NewsletterStatusReader($this->moduleManager, $objectManager, $this->storeManager, $this->logger);

        $this->assertSame(NewsletterStatus::Unknown, $reader->getStatus('jane.doe@example.com', 5));
    }

    public function testTheModuleAndTheStatusCheckCanBeReplaced(): void
    {
        $moduleManager = $this->createMock(ModuleManager::class);
        $moduleManager->method('isEnabled')->willReturnCallback(
            static fn (string $module): bool => $module === 'Vendor_Newsletter'
        );
        $objectManager = $this->createMock(ObjectManagerInterface::class);
        $objectManager->expects($this->once())->method('get')->with('Vendor\Newsletter\StatusCheck')
            ->willReturn($this->statusCheck(self::STATUS_CHECK));
        $this->answer = SubscriptionStatusStub::Confirmed;

        $reader = new NewsletterStatusReader(
            $moduleManager,
            $objectManager,
            $this->storeManager,
            $this->logger,
            'Vendor_Newsletter',
            'Vendor\Newsletter\StatusCheck'
        );

        $this->assertSame(NewsletterStatus::Confirmed, $reader->getStatus('jane.doe@example.com', 5));
    }

    private function reader(): NewsletterStatusReader
    {
        return new NewsletterStatusReader($this->moduleManager, $this->objectManager, $this->storeManager, $this->logger);
    }

    private function statusCheck(string $type): object
    {
        $this->assertSame(self::STATUS_CHECK, $type);

        return new class ($this) {
            public function __construct(private readonly NewsletterStatusReaderTest $test)
            {
            }

            public function getStatus(string $email, int $websiteId): SubscriptionStatusStub
            {
                return $this->test->answer($email, $websiteId);
            }
        };
    }

    /**
     * Answer of the fake status check; records the question
     *
     * @param string $email
     * @param int $websiteId
     * @return SubscriptionStatusStub
     */
    public function answer(string $email, int $websiteId): SubscriptionStatusStub
    {
        $this->asked[] = [$email, $websiteId];
        if ($this->checkFailure !== null) {
            throw $this->checkFailure;
        }

        return $this->answer;
    }
}
