<?php declare(strict_types=1);

namespace PixelPerfect\SalesEmail\Setup\Patch\Data;

use Magento\Cms\Api\BlockRepositoryInterface;
use Magento\Cms\Model\BlockFactory;
use Magento\Cms\Model\ResourceModel\Block\CollectionFactory;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Installs the neutral default invitation block; an existing block with the identifier is left untouched.
 * The button links to the store home with a #newsletter anchor because core has no newsletter sign-up page.
 */
class AddDefaultInvitationBlock implements DataPatchInterface
{
    public const IDENTIFIER = 'pp_sales_email_invitation';

    public const CONTENT = <<<'HTML'
<table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation" style="background-color:#f6f6f4;border:1px solid #e3e3e3">
<tbody>
<tr><td align="left" style="padding:24px 24px 8px 24px;font-family:Arial,Helvetica,sans-serif;font-size:12px;letter-spacing:1px;text-transform:uppercase;color:#3a5a40"><strong>Newsletter</strong></td></tr>
<tr><td align="left" style="padding:0 24px 16px 24px;font-family:Arial,Helvetica,sans-serif;font-size:20px;line-height:28px;color:#2b2b2b"><strong>Be the first to hear about new products and offers.</strong></td></tr>
<tr><td style="padding:0 24px 16px 24px">
<table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation"><tbody>
<tr><td width="20" valign="top" style="font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:22px;color:#3a5a40"><strong>+</strong></td><td style="font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:22px;color:#2b2b2b">Early access to new arrivals</td></tr>
<tr><td width="20" valign="top" style="font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:22px;color:#3a5a40"><strong>+</strong></td><td style="font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:22px;color:#2b2b2b">Special offers for subscribers</td></tr>
<tr><td width="20" valign="top" style="font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:22px;color:#3a5a40"><strong>+</strong></td><td style="font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:22px;color:#2b2b2b">Tips and inspiration, a few times a month</td></tr>
</tbody></table>
</td></tr>
<tr><td align="left" style="padding:0 24px 16px 24px"><a href="{{store url=''}}#newsletter" style="display:inline-block;padding:12px 24px;background-color:#3a5a40;color:#ffffff;font-family:Arial,Helvetica,sans-serif;font-size:15px;text-decoration:none">Sign up for the newsletter</a></td></tr>
<tr><td align="left" style="padding:0 24px 24px 24px;font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:18px;color:#6b6b6b">We will send you a confirmation email first. You can unsubscribe at any time with one click.</td></tr>
</tbody>
</table>
HTML;

    /**
     * @param BlockFactory $blockFactory
     * @param BlockRepositoryInterface $blockRepository
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(
        private readonly BlockFactory $blockFactory,
        private readonly BlockRepositoryInterface $blockRepository,
        private readonly CollectionFactory $collectionFactory
    ) {
    }

    /**
     * Create the block unless one with the identifier exists
     *
     * @return $this
     */
    public function apply(): self
    {
        $existing = $this->collectionFactory->create()->addFieldToFilter('identifier', self::IDENTIFIER);
        if ($existing->getSize() > 0) {
            return $this;
        }

        $block = $this->blockFactory->create();
        $block->setIdentifier(self::IDENTIFIER);
        $block->setTitle('Sales email invitation');
        $block->setContent(self::CONTENT);
        $block->setIsActive(true);
        $block->setData('stores', [0]);
        $this->blockRepository->save($block);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getAliases(): array
    {
        return [];
    }
}
