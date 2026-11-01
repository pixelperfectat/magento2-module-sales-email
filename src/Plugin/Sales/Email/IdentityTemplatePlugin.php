<?php declare(strict_types=1);

namespace PixelPerfect\SalesEmail\Plugin\Sales\Email;

use Magento\Sales\Model\Order\Email\Container\Container;
use PixelPerfect\SalesEmail\Api\ConfigInterface;

/**
 * Falls back to the core email templates for store views where the modern layout is switched off
 */
class IdentityTemplatePlugin
{
    /**
     * @param ConfigInterface $config
     * @param array<string, string> $coreTemplateIds Module template id => core template id
     */
    public function __construct(
        private readonly ConfigInterface $config,
        private readonly array $coreTemplateIds = [
            'pp_sales_email_order_template' => 'sales_email_order_template',
            'pp_sales_email_order_guest_template' => 'sales_email_order_guest_template',
            'pp_sales_email_shipment_template' => 'sales_email_shipment_template',
            'pp_sales_email_shipment_guest_template' => 'sales_email_shipment_guest_template',
        ]
    ) {
    }

    /**
     * Replace the module template id by the core one when the store view has the modern layout switched off
     *
     * @param Container $subject
     * @param mixed $result
     * @return mixed
     */
    public function afterGetTemplateId(Container $subject, mixed $result): mixed
    {
        return $this->resolve($subject, $result);
    }

    /**
     * Replace the module guest template id by the core one when the store view has the modern layout switched off
     *
     * @param Container $subject
     * @param mixed $result
     * @return mixed
     */
    public function afterGetGuestTemplateId(Container $subject, mixed $result): mixed
    {
        return $this->resolve($subject, $result);
    }

    /**
     * Map a module template id to its core counterpart for disabled store views, leave everything else alone
     *
     * @param Container $subject
     * @param mixed $templateId
     * @return mixed
     */
    private function resolve(Container $subject, mixed $templateId): mixed
    {
        if (!is_string($templateId) || !isset($this->coreTemplateIds[$templateId])) {
            return $templateId;
        }

        $storeId = (int)$subject->getStore()->getId();

        return $this->config->isEnabled($storeId) ? $templateId : $this->coreTemplateIds[$templateId];
    }
}
