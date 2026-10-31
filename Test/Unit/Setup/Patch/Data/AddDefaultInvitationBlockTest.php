<?php declare(strict_types=1);

namespace PixelPerfect\SalesEmail\Test\Unit\Setup\Patch\Data;

use PHPUnit\Framework\TestCase;
use PixelPerfect\SalesEmail\Api\Data\SanitizedHtmlInterfaceFactory;
use PixelPerfect\SalesEmail\Service\EmailHtmlSanitizer;
use PixelPerfect\SalesEmail\Setup\Patch\Data\AddDefaultInvitationBlock;
use Psr\Log\LoggerInterface;

class AddDefaultInvitationBlockTest extends TestCase
{
    private EmailHtmlSanitizer $sanitizer;

    protected function setUp(): void
    {
        $this->sanitizer = new EmailHtmlSanitizer(new SanitizedHtmlInterfaceFactory(), $this->createMock(LoggerInterface::class));
    }

    public function testDefaultContentPassesTheSanitizerUntouched(): void
    {
        $result = $this->sanitizer->sanitize(AddDefaultInvitationBlock::CONTENT);

        $this->assertSame([], $result->getRemoved());
        $this->assertSame($result->getHtml(), $this->sanitizer->sanitize($result->getHtml())->getHtml());
    }

    public function testCmsFilteredContentPassesUnchanged(): void
    {
        $filtered = preg_replace(
            "/\\{\\{store url=''\\}\\}/",
            'https://example.com/',
            AddDefaultInvitationBlock::CONTENT
        );
        $this->assertStringContainsString('href="https://example.com/#newsletter"', (string)$filtered);

        $result = $this->sanitizer->sanitize((string)$filtered);

        $this->assertSame([], $result->getRemoved());
        $this->assertSame(
            preg_replace('/\\s+/', '', (string)$filtered),
            preg_replace('/\\s+/', '', $result->getHtml())
        );
    }

    public function testDefaultContentHasTheAgreedCopy(): void
    {
        $content = AddDefaultInvitationBlock::CONTENT;

        $this->assertStringContainsString('Newsletter', $content);
        $this->assertStringContainsString('Be the first to hear about new products and offers.', $content);
        $this->assertStringContainsString('Sign up for the newsletter', $content);
        $this->assertSame(3, substr_count($content, '>+<'));
    }

    public function testIdentifierIsFixed(): void
    {
        $this->assertSame('pp_sales_email_invitation', AddDefaultInvitationBlock::IDENTIFIER);
    }
}
