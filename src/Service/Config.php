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
        $value = $this->scopeConfig->getValue(self::XML_PATH_CMS_BLOCK, ScopeInterface::SCOPE_STORE, $storeId);
        $value = is_string($value) ? trim($value) : '';

        return $value === '' ? null : $value;
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
}
