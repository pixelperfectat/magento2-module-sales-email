<?php declare(strict_types=1);

namespace PixelPerfect\SalesEmail\ViewModel;

use Magento\Cms\Api\GetBlockByIdentifierInterface;
use Magento\Cms\Model\Template\Filter;
use Magento\Cms\Model\Template\FilterProvider;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use PixelPerfect\SalesEmail\Api\ConfigInterface;
use PixelPerfect\SalesEmail\Api\Data\NewsletterStatus;
use PixelPerfect\SalesEmail\Api\Service\EmailHtmlSanitizerInterface;
use PixelPerfect\SalesEmail\Api\Service\NewsletterStatusReaderInterface;
use Psr\Log\LoggerInterface;

class Invitation implements ArgumentInterface
{
    /**
     * Block variable holding the sign-up source of the email, for links such as
     * {{store url='' _query_nl_source=$nl_source}}
     */
    public const SOURCE_VARIABLE = 'nl_source';

    /**
     * @param ConfigInterface $config
     * @param GetBlockByIdentifierInterface $getBlockByIdentifier
     * @param FilterProvider $filterProvider
     * @param EmailHtmlSanitizerInterface $sanitizer
     * @param LoggerInterface $logger
     * @param NewsletterStatusReaderInterface $statusReader
     * @param array<string, string> $sourceCodes email type => sign-up source code handed to the block
     */
    public function __construct(
        private readonly ConfigInterface $config,
        private readonly GetBlockByIdentifierInterface $getBlockByIdentifier,
        private readonly FilterProvider $filterProvider,
        private readonly EmailHtmlSanitizerInterface $sanitizer,
        private readonly LoggerInterface $logger,
        private readonly NewsletterStatusReaderInterface $statusReader,
        private readonly array $sourceCodes = [
            ConfigInterface::EMAIL_TYPE_ORDER => 'order_email',
            ConfigInterface::EMAIL_TYPE_SHIPMENT => 'shipment_email',
        ]
    ) {
    }

    /**
     * Email-safe invitation HTML for the email type, store and recipient, empty when there is nothing to show
     *
     * Pending and confirmed subscribers get their own block instead of the invitation; recipients who
     * unsubscribed get none when the store hides it for them.
     *
     * @param string $emailType order or shipment
     * @param int $storeId
     * @param string $email the order's email address
     * @return string
     */
    public function getHtml(string $emailType, int $storeId, string $email = ''): string
    {
        if (!$this->config->isInvitationEnabledFor($emailType, $storeId)) {
            return '';
        }
        $identifier = $this->blockIdFor($this->statusReader->getStatus($email, $storeId), $storeId);
        if ($identifier === null) {
            return '';
        }

        try {
            $block = $this->getBlockByIdentifier->execute($identifier, $storeId);
            if (!$block->isActive()) {
                return '';
            }
            /** @var Filter $filter */
            $filter = $this->filterProvider->getBlockFilter();
            $filter->setVariables([self::SOURCE_VARIABLE => $this->sourceCodes[$emailType] ?? '']);
            $filtered = $filter->setStoreId($storeId)->filter($block->getContent());

            return $this->sanitizer->sanitize($filtered)->getHtml();
        } catch (NoSuchEntityException) {
            return '';
        } catch (\Throwable $e) {
            $this->logger->error('Invitation block could not be rendered: ' . $e->getMessage(), ['exception' => $e]);

            return '';
        }
    }

    /**
     * CMS block for the recipient's newsletter status, null when they get nothing
     *
     * @param NewsletterStatus $status
     * @param int $storeId
     * @return string|null
     */
    private function blockIdFor(NewsletterStatus $status, int $storeId): ?string
    {
        return match ($status) {
            NewsletterStatus::Pending => $this->config->getPendingBlockId($storeId),
            NewsletterStatus::Confirmed => $this->config->getConfirmedBlockId($storeId),
            NewsletterStatus::Unsubscribed => $this->config->isHiddenForUnsubscribed($storeId)
                ? null
                : $this->config->getInvitationBlockId($storeId),
            NewsletterStatus::Unknown, NewsletterStatus::NotSubscribed => $this->config->getInvitationBlockId($storeId),
        };
    }
}
