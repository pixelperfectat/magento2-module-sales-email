<?php declare(strict_types=1);

namespace PixelPerfect\SalesEmail\Service;

use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use PixelPerfect\SalesEmail\Api\Data\NewsletterStatus;
use PixelPerfect\SalesEmail\Api\Service\NewsletterStatusReaderInterface;
use Psr\Log\LoggerInterface;

/**
 * Reads the status from the newsletter sign-up module when it is installed, which this module only suggests
 *
 * The status check is fetched from the object manager by name, on purpose: a constructor argument typed with
 * a class of a module that is not installed breaks setup:di:compile. Its status is mapped by case name for
 * the same reason, so no class of the optional module is ever referenced.
 */
class NewsletterStatusReader implements NewsletterStatusReaderInterface
{
    /**
     * A string, not ::class: the interface may not exist, and nothing here may depend on it
     */
    // phpcs:ignore Magento2.PHP.LiteralNamespaces
    private const STATUS_CHECK_TYPE = 'PixelPerfect\NewsletterSignup\Api\Service\SubscriptionStatusCheckInterface';

    /**
     * @param ModuleManager $moduleManager
     * @param ObjectManagerInterface $objectManager
     * @param StoreManagerInterface $storeManager
     * @param LoggerInterface $logger
     * @param string $moduleName module that provides the status check
     * @param string $statusCheckType its status check, getStatus(string $email, int $websiteId) returning an enum
     */
    public function __construct(
        private readonly ModuleManager $moduleManager,
        private readonly ObjectManagerInterface $objectManager,
        private readonly StoreManagerInterface $storeManager,
        private readonly LoggerInterface $logger,
        private readonly string $moduleName = 'PixelPerfect_NewsletterSignup',
        private readonly string $statusCheckType = self::STATUS_CHECK_TYPE
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getStatus(string $email, int $storeId): NewsletterStatus
    {
        if (trim($email) === '' || !$this->moduleManager->isEnabled($this->moduleName)) {
            return NewsletterStatus::Unknown;
        }

        try {
            $websiteId = (int)$this->storeManager->getStore($storeId)->getWebsiteId();
            $status = $this->objectManager->get($this->statusCheckType)->getStatus($email, $websiteId);
        } catch (\Throwable $e) {
            $this->logger->error('Newsletter status could not be read: ' . $e->getMessage(), ['exception' => $e]);

            return NewsletterStatus::Unknown;
        }

        return match ($status instanceof \UnitEnum ? $status->name : '') {
            'NotSubscribed' => NewsletterStatus::NotSubscribed,
            'Pending' => NewsletterStatus::Pending,
            'Confirmed' => NewsletterStatus::Confirmed,
            'Unsubscribed' => NewsletterStatus::Unsubscribed,
            default => NewsletterStatus::Unknown,
        };
    }
}
