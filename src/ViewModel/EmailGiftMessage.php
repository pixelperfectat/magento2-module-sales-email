<?php

declare(strict_types=1);

namespace PixelPerfect\SalesEmail\ViewModel;

use Magento\Framework\DataObject;
use Magento\Framework\Escaper;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\GiftMessage\Helper\Message as GiftMessageHelper;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item as OrderItem;

class EmailGiftMessage implements ArgumentInterface
{
    /**
     * @param GiftMessageHelper $helper
     * @param Escaper $escaper
     */
    public function __construct(
        private readonly GiftMessageHelper $helper,
        private readonly Escaper $escaper
    ) {
    }

    /**
     * Email-safe gift message block of an order item, empty when it has none
     *
     * @param OrderItem $item
     * @return string
     */
    public function getItemHtml(OrderItem $item): string
    {
        $id = (int)$item->getGiftMessageId();

        return $id > 0 ? $this->render($this->helper->getGiftMessage($id)) : '';
    }

    /**
     * Email-safe gift message block of the order, empty when it has none or messages are not allowed
     *
     * @param Order $order
     * @return string
     */
    public function getOrderHtml(Order $order): string
    {
        $id = (int)$order->getGiftMessageId();
        if ($id === 0 || !$this->helper->isMessagesAllowed('order', $order, $order->getStore())) {
            return '';
        }

        return $this->render($this->helper->getGiftMessage($id));
    }

    /**
     * Build the table markup, everything escaped
     *
     * @param DataObject|null $message
     * @return string
     */
    private function render(?DataObject $message): string
    {
        if ($message === null || !$message->getData('message')) {
            return '';
        }

        $rows = [
            (string)__('From:') => (string)$message->getData('sender'),
            (string)__('To:') => (string)$message->getData('recipient'),
            (string)__('Message:') => (string)$message->getData('message'),
        ];
        $html = '<table role="presentation" class="pp-gift" width="100%" cellpadding="0" cellspacing="0" border="0">'
            . '<tr><td class="pp-gift-cell" bgcolor="#f6f6f4"><strong>'
            . $this->escaper->escapeHtml((string)__('Gift Message')) . '</strong>';
        foreach ($rows as $label => $value) {
            $html .= '<br /><span class="pp-gift-label">' . $this->escaper->escapeHtml($label) . '</span> '
                . $this->escaper->escapeHtml($value);
        }

        return $html . '</td></tr></table>';
    }
}
