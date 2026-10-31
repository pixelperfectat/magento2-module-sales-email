<?php declare(strict_types=1);

namespace PixelPerfect\SalesEmail\Api;

interface ConfigInterface
{
    public const XML_PATH_ENABLED = 'pp_sales_email/general/enabled';
    public const XML_PATH_CMS_BLOCK = 'pp_sales_email/invitation/cms_block';
    public const XML_PATH_ENABLED_FOR = 'pp_sales_email/invitation/enabled_for';

    public const EMAIL_TYPE_ORDER = 'order';
    public const EMAIL_TYPE_SHIPMENT = 'shipment';
    public const EMAIL_TYPES = [self::EMAIL_TYPE_ORDER, self::EMAIL_TYPE_SHIPMENT];

    /**
     * Whether the modern email layout is used for the store view
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isEnabled(?int $storeId = null): bool;

    /**
     * Identifier of the CMS block shown as invitation, null when none is configured
     *
     * @param int|null $storeId
     * @return string|null
     */
    public function getInvitationBlockId(?int $storeId = null): ?string;

    /**
     * Whether the invitation block belongs into the given email type (order or shipment)
     *
     * @param string $emailType
     * @param int|null $storeId
     * @return bool
     */
    public function isInvitationEnabledFor(string $emailType, ?int $storeId = null): bool;
}
