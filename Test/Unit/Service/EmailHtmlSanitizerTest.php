<?php declare(strict_types=1);

namespace PixelPerfect\SalesEmail\Test\Unit\Service;

use PHPUnit\Framework\TestCase;
use PixelPerfect\SalesEmail\Api\Data\SanitizedHtmlInterfaceFactory;
use PixelPerfect\SalesEmail\Service\EmailHtmlSanitizer;
use Psr\Log\LoggerInterface;

class EmailHtmlSanitizerTest extends TestCase
{
    private EmailHtmlSanitizer $sanitizer;

    protected function setUp(): void
    {
        $this->sanitizer = new EmailHtmlSanitizer(
            new SanitizedHtmlInterfaceFactory(),
            $this->createMock(LoggerInterface::class)
        );
    }

    public function testStripsScriptWithContent(): void
    {
        $result = $this->sanitizer->sanitize('<p>Hi</p><script>alert("x")</script>');

        $this->assertSame('<p>Hi</p>', $result->getHtml());
        $this->assertSame(['script'], $result->getRemoved());
    }

    public function testStripsStyleAndSvgSubtrees(): void
    {
        $result = $this->sanitizer->sanitize(
            '<style>p{color:red}</style><p>Keep</p><svg><circle r="1"></circle></svg>'
        );

        $this->assertSame('<p>Keep</p>', $result->getHtml());
        $this->assertEqualsCanonicalizing(['style', 'svg'], $result->getRemoved());
    }

    public function testStripsEventAttributes(): void
    {
        $result = $this->sanitizer->sanitize('<p onclick="evil()" style="color:#111">Hi</p>');

        $this->assertStringNotContainsString('onclick', $result->getHtml());
        $this->assertStringContainsString('style="color:#111"', $result->getHtml());
        $this->assertSame(['onclick attribute'], $result->getRemoved());
    }

    /**
     * @dataProvider unsafeUrlProvider
     */
    public function testStripsUnsafeUrls(string $href): void
    {
        $result = $this->sanitizer->sanitize('<a href="' . $href . '">x</a>');

        $this->assertStringNotContainsString('href', $result->getHtml());
        $this->assertSame(['href (unsafe URL)'], $result->getRemoved());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unsafeUrlProvider(): array
    {
        return [
            'plain' => ['javascript:alert(1)'],
            'mixed case' => ['JaVaScRiPt:alert(1)'],
            'whitespace' => [" \tjava\nscript:alert(1)"],
            'entity encoded colon' => ['javascript&#58;alert(1)'],
            'data' => ['data:text/html;base64,AAAA'],
            'vbscript' => ['VBScript:msgbox(1)'],
        ];
    }

    public function testStripsUnsafeImageSource(): void
    {
        $result = $this->sanitizer->sanitize('<img src="data:image/png;base64,AAAA" alt="x">');

        $this->assertStringNotContainsString('src', $result->getHtml());
        $this->assertSame(['src (unsafe URL)'], $result->getRemoved());
    }

    public function testKeepsTablesInlineStylesLinksAndImages(): void
    {
        $html = '<table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation">'
            . '<tbody><tr><td align="center" style="padding:8px;color:#2b2b2b">'
            . '<a href="https://example.com/x?a=1&amp;b=2" title="t">Link</a>'
            . '<img src="https://example.com/i.png" alt="i" width="64" height="64">'
            . '</td></tr></tbody></table>';

        $result = $this->sanitizer->sanitize($html);

        $this->assertSame([], $result->getRemoved());
        $this->assertStringContainsString('<table width="100%"', $result->getHtml());
        $this->assertStringContainsString('style="padding:8px;color:#2b2b2b"', $result->getHtml());
        $this->assertStringContainsString('href="https://example.com/x?a=1&amp;b=2"', $result->getHtml());
        $this->assertStringContainsString('src="https://example.com/i.png"', $result->getHtml());
    }

    public function testKeepsRelativeAnchorMailtoAndTelLinks(): void
    {
        $result = $this->sanitizer->sanitize(
            '<a href="#newsletter">a</a><a href="mailto:jane.doe@example.com">b</a><a href="tel:+100">c</a>'
        );

        $this->assertSame([], $result->getRemoved());
    }

    public function testUnknownTagIsUnwrappedAndReported(): void
    {
        $result = $this->sanitizer->sanitize('<section><p>Inner</p></section>');

        $this->assertSame('<p>Inner</p>', $result->getHtml());
        $this->assertSame(['section'], $result->getRemoved());
    }

    public function testDangerousStyleValueIsRemoved(): void
    {
        $result = $this->sanitizer->sanitize('<p style="width:expression(alert(1))">x</p>');

        $this->assertStringNotContainsString('style', $result->getHtml());
        $this->assertSame(['style (unsafe value)'], $result->getRemoved());
    }

    /**
     * @dataProvider unsafeStyleProvider
     */
    public function testHardenedStyleChecks(string $style): void
    {
        $result = $this->sanitizer->sanitize('<p style="' . $style . '">x</p>');

        $this->assertStringNotContainsString('style', $result->getHtml());
        $this->assertSame(['style (unsafe value)'], $result->getRemoved());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unsafeStyleProvider(): array
    {
        return [
            'css escape' => ['width:exp\\72 ession(alert(1))'],
            'comment split' => ['width:expr/**/ession(alert(1))'],
            'escaped url scheme' => ['background:url(java\\73 cript:alert(1))'],
            'escaped behavior' => ['beh\\61 vior:url(x.htc)'],
            'plain behavior' => ['behavior:url(x.htc)'],
            'data url' => ['background:url(data:image/png;base64,AAAA)'],
            'quoted data url' => ["background:url('data:text/html,x')"],
        ];
    }

    public function testHttpsUrlInStyleIsKept(): void
    {
        $result = $this->sanitizer->sanitize('<p style="background:url(https://example.com/a.png)">x</p>');

        $this->assertSame([], $result->getRemoved());
    }

    public function testStrayClosingTagDoesNotLoseFollowingContent(): void
    {
        $result = $this->sanitizer->sanitize('<p>a</p></div><p>lost</p>');

        $this->assertSame('<p>a</p><p>lost</p>', $result->getHtml());
    }

    public function testCommentsAreDropped(): void
    {
        $result = $this->sanitizer->sanitize('<p>a</p><!-- secret --><p>b</p>');

        $this->assertSame('<p>a</p><p>b</p>', $result->getHtml());
    }

    public function testUtf8SurvivesUnchanged(): void
    {
        $result = $this->sanitizer->sanitize('<p>Grüße – 日本 &amp; Co</p>');

        $this->assertSame('<p>Grüße – 日本 &amp; Co</p>', $result->getHtml());
    }

    public function testEmptyInputGivesEmptyResult(): void
    {
        $result = $this->sanitizer->sanitize("  \n ");

        $this->assertSame('', $result->getHtml());
        $this->assertSame([], $result->getRemoved());
    }

    public function testRemovedTagsAreListedOnce(): void
    {
        $result = $this->sanitizer->sanitize('<script>a</script><p>x</p><script>b</script>');

        $this->assertSame(['script'], $result->getRemoved());
    }

    public function testNeverThrowsAndLogsOnFailure(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $factory = $this->createMock(SanitizedHtmlInterfaceFactory::class);
        $factory->method('create')->willReturnCallback(
            static function (array $data) {
                static $first = true;
                if ($first) {
                    $first = false;
                    throw new \RuntimeException('boom');
                }
                return (new SanitizedHtmlInterfaceFactory())->create($data);
            }
        );
        $logger->expects($this->once())->method('error');

        $result = (new EmailHtmlSanitizer($factory, $logger))->sanitize('<p>x</p>');

        $this->assertSame('', $result->getHtml());
    }
}
