<?php declare(strict_types=1);

namespace PixelPerfect\SalesEmail\Service;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use PixelPerfect\SalesEmail\Api\ConfigInterface;

readonly class Config implements ConfigInterface
{
    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        private ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * @inheritDoc
     */
    public function isEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @inheritDoc
     */
    public function getInvitationBlockId(?int $storeId = null): ?string
    {
        return $this->blockId(self::XML_PATH_CMS_BLOCK, $storeId);
    }

    /**
     * @inheritDoc
     */
    public function getPendingBlockId(?int $storeId = null): ?string
    {
        return $this->blockId(self::XML_PATH_PENDING_CMS_BLOCK, $storeId);
    }

    /**
     * @inheritDoc
     */
    public function getConfirmedBlockId(?int $storeId = null): ?string
    {
        return $this->blockId(self::XML_PATH_CONFIRMED_CMS_BLOCK, $storeId);
    }

    /**
     * @inheritDoc
     */
    public function isHiddenForUnsubscribed(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_HIDE_UNSUBSCRIBED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @inheritDoc
     */
    public function isInvitationEnabledFor(string $emailType, ?int $storeId = null): bool
    {
        $value = $this->scopeConfig->getValue(self::XML_PATH_ENABLED_FOR, ScopeInterface::SCOPE_STORE, $storeId);
        if (!is_string($value)) {
            return false;
        }

        return in_array($emailType, array_map('trim', explode(',', $value)), true);
    }

    /**
     * Trimmed CMS block identifier at the config path, null when empty
     *
     * @param string $path
     * @param int|null $storeId
     * @return string|null
     */
    private function blockId(string $path, ?int $storeId): ?string
    {
        $value = $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $storeId);
        $value = is_string($value) ? trim($value) : '';

        return $value === '' ? null : $value;
    }
}
