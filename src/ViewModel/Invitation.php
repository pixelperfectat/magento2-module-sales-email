<?php declare(strict_types=1);

namespace PixelPerfect\SalesEmail\ViewModel;

use Magento\Cms\Api\GetBlockByIdentifierInterface;
use Magento\Cms\Model\Template\Filter;
use Magento\Cms\Model\Template\FilterProvider;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use PixelPerfect\SalesEmail\Api\ConfigInterface;
use PixelPerfect\SalesEmail\Api\Service\EmailHtmlSanitizerInterface;
use Psr\Log\LoggerInterface;

class Invitation implements ArgumentInterface
{
    /**
     * @param ConfigInterface $config
     * @param GetBlockByIdentifierInterface $getBlockByIdentifier
     * @param FilterProvider $filterProvider
     * @param EmailHtmlSanitizerInterface $sanitizer
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly ConfigInterface $config,
        private readonly GetBlockByIdentifierInterface $getBlockByIdentifier,
        private readonly FilterProvider $filterProvider,
        private readonly EmailHtmlSanitizerInterface $sanitizer,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Email-safe invitation HTML for the email type and store, empty when there is nothing to show
     *
     * @param string $emailType order or shipment
     * @param int $storeId
     * @return string
     */
    public function getHtml(string $emailType, int $storeId): string
    {
        if (!$this->config->isInvitationEnabledFor($emailType, $storeId)) {
            return '';
        }
        $identifier = $this->config->getInvitationBlockId($storeId);
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
            $filtered = $filter->setStoreId($storeId)->filter($block->getContent());

            return $this->sanitizer->sanitize($filtered)->getHtml();
        } catch (NoSuchEntityException) {
            return '';
        } catch (\Throwable $e) {
            $this->logger->error('Invitation block could not be rendered: ' . $e->getMessage(), ['exception' => $e]);

            return '';
        }
    }
}
