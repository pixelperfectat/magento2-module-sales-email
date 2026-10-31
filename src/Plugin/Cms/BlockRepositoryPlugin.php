<?php declare(strict_types=1);

namespace PixelPerfect\SalesEmail\Plugin\Cms;

use Magento\Cms\Api\BlockRepositoryInterface;
use Magento\Cms\Api\Data\BlockInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use PixelPerfect\SalesEmail\Api\ConfigInterface;
use PixelPerfect\SalesEmail\Api\Service\EmailHtmlSanitizerInterface;
use Psr\Log\LoggerInterface;

/**
 * Warns the admin when a block used as email invitation holds markup that is stripped from emails.
 * Registered for the adminhtml area only; never blocks the save.
 */
class BlockRepositoryPlugin
{
    /**
     * @param ConfigInterface $config
     * @param EmailHtmlSanitizerInterface $sanitizer
     * @param ManagerInterface $messageManager
     * @param StoreManagerInterface $storeManager
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly ConfigInterface $config,
        private readonly EmailHtmlSanitizerInterface $sanitizer,
        private readonly ManagerInterface $messageManager,
        private readonly StoreManagerInterface $storeManager,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Add a warning listing what the email sanitizer will strip from the saved block
     *
     * @param BlockRepositoryInterface $subject
     * @param BlockInterface $result
     * @param BlockInterface $block
     * @return BlockInterface
     */
    public function afterSave(BlockRepositoryInterface $subject, BlockInterface $result, BlockInterface $block): BlockInterface
    {
        try {
            $identifier = (string)$block->getIdentifier();
            if ($identifier === '' || !$this->isInvitationBlock($identifier)) {
                return $result;
            }
            $removed = $this->sanitizer->sanitize((string)$block->getContent())->getRemoved();
            if ($removed !== []) {
                $this->messageManager->addWarningMessage((string)__(
                    'The CMS block "%1" is used as email invitation. These elements are not email-safe and will be '
                    . 'removed from the emails: %2.',
                    $identifier,
                    implode(', ', $removed)
                ));
            }
        } catch (\Throwable $e) {
            $this->logger->error('Invitation block check failed: ' . $e->getMessage(), ['exception' => $e]);
        }

        return $result;
    }

    /**
     * Whether any scope (including the default one) configures the identifier as invitation block
     *
     * @param string $identifier
     * @return bool
     */
    private function isInvitationBlock(string $identifier): bool
    {
        foreach ($this->storeManager->getStores(true) as $store) {
            if ($this->config->getInvitationBlockId((int)$store->getId()) === $identifier) {
                return true;
            }
        }

        return false;
    }
}
