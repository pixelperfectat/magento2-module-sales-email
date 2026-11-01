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

    public function testHttpLinksAreKept(): void
    {
        $result = $this->sanitizer->sanitize('<a href="http://example.com/a">x</a>');

        $this->assertSame('<a href="http://example.com/a">x</a>', $result->getHtml());
        $this->assertSame([], $result->getRemoved());
    }

    public function testRelativeUrlsContainingColonsAreKept(): void
    {
        $html = '<a href="/page?ref=x:y">a</a><a href="#a:b">b</a><img src="img/a:b.png" alt="c">';

        $result = $this->sanitizer->sanitize($html);

        $this->assertSame([], $result->getRemoved());
        $this->assertSame($html, $result->getHtml());
    }

    /**
     * @dataProvider encodedSchemeProvider
     */
    public function testEntityEncodedSchemesAreStripped(string $href): void
    {
        $result = $this->sanitizer->sanitize('<a href="' . $href . '">x</a>');

        $this->assertSame('<a>x</a>', $result->getHtml());
        $this->assertSame(['href (unsafe URL)'], $result->getRemoved());
    }

    /**
     * Double-encoded in the markup, so the attribute value still holds an entity after parsing.
     *
     * @return array<string, array{string}>
     */
    public static function encodedSchemeProvider(): array
    {
        return [
            'named colon' => ['javascript&amp;colon;alert(1)'],
            'named tab' => ['java&amp;Tab;script:alert(1)'],
            'named newline' => ['java&amp;NewLine;script:alert(1)'],
            'numeric upper-case letter' => ['&amp;#74;AVASCRIPT:alert(1)'],
            'hex letter' => ['&amp;#x6A;avascript:alert(1)'],
        ];
    }

    public function testUppercaseStyleKeywordsAreRejected(): void
    {
        foreach (['width:EXPRESSION(alert(1))', 'BEHAVIOR:url(x.htc)', 'background:url(x);@IMPORT x'] as $style) {
            $result = $this->sanitizer->sanitize('<p style="' . $style . '">x</p>');

            $this->assertSame('<p>x</p>', $result->getHtml(), $style);
            $this->assertSame(['style (unsafe value)'], $result->getRemoved(), $style);
        }
    }

    /**
     * @dataProvider unsafeStyleUrlProvider
     */
    public function testStyleUrlsWithOtherSchemesAreRejected(string $style): void
    {
        $result = $this->sanitizer->sanitize('<p style="' . $style . '">x</p>');

        $this->assertSame('<p>x</p>', $result->getHtml());
        $this->assertSame(['style (unsafe value)'], $result->getRemoved());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unsafeStyleUrlProvider(): array
    {
        return [
            'https hidden behind another scheme' => ['background:url(data:text/html,http://example.com)'],
            'upper-case scheme' => ['background:url(DATA:image/png;base64,AAAA)'],
            'ftp' => ['background:url(ftp://example.com/a.png)'],
        ];
    }

    public function testStyleUrlsOverHttpAndRelativeAreKept(): void
    {
        $style = 'background:url(HTTPS://example.com/a.png);list-style:url(http://example.com/b.png);'
            . 'background-image:url(/img/a:b.png);border-image:url( \'img/c.png\' )';

        $result = $this->sanitizer->sanitize('<p style="' . $style . '">x</p>');

        $this->assertSame([], $result->getRemoved());
        $this->assertStringContainsString('style=', $result->getHtml());
    }

    public function testStyleWithoutUrlIsKept(): void
    {
        $result = $this->sanitizer->sanitize('<p style="color:#111;margin:0">x</p>');

        $this->assertSame('<p style="color:#111;margin:0">x</p>', $result->getHtml());
    }

    public function testRemovalContinuesAfterCommentsTextAndDroppedTags(): void
    {
        $result = $this->sanitizer->sanitize(
            '<!-- c --><script>1</script>text<style>2</style><section>s</section><b onclick="x">y</b>'
        );

        $this->assertSame('texts<b>y</b>', $result->getHtml());
        $this->assertSame(
            ['script', 'style', 'section', 'onclick attribute'],
            $result->getRemoved()
        );
    }

    public function testNestedContentIsCleanedRecursively(): void
    {
        $result = $this->sanitizer->sanitize(
            '<div><table><tr><td><script>1</script><span><article onclick="x">deep</article></span></td></tr></table></div>'
        );

        $this->assertSame('<div><table><tr><td><span>deep</span></td></tr></table></div>', $result->getHtml());
        $this->assertSame(['script', 'article'], $result->getRemoved());
    }

    public function testMarkupIsCaseInsensitive(): void
    {
        $result = $this->sanitizer->sanitize('<SCRIPT>1</SCRIPT><P ONCLICK="x" STYLE="color:red">a</P><A HREF="JAVASCRIPT:1">b</A>');

        $this->assertSame('<p style="color:red">a</p><a>b</a>', $result->getHtml());
        $this->assertSame(['script', 'onclick attribute', 'href (unsafe URL)'], $result->getRemoved());
    }

    public function testRemovedLabelsAreUnwrappedUnsafeUrlAndUnsafeValueDistinct(): void
    {
        $result = $this->sanitizer->sanitize(
            '<p style="behavior:url(x)" onclick="a()"><a href="javascript:1">x</a><font>f</font></p>'
        );

        $this->assertSame(['href (unsafe URL)', 'font', 'style (unsafe value)', 'onclick attribute'], $result->getRemoved());
    }

    public function testSurroundingWhitespaceIsTrimmed(): void
    {
        $result = $this->sanitizer->sanitize("\n  <p>a</p>  \n");

        $this->assertSame('<p>a</p>', $result->getHtml());
    }

    public function testPlainTextAndInlineMarkupSurvive(): void
    {
        $result = $this->sanitizer->sanitize('Hello <b>big</b> world');

        $this->assertSame('Hello <b>big</b> world', $result->getHtml());
        $this->assertSame([], $result->getRemoved());
    }

    public function testBlankInputDoesNotParseAndCreatesAnEmptyResultOnce(): void
    {
        $calls = [];
        $factory = $this->createMock(SanitizedHtmlInterfaceFactory::class);
        $factory->method('create')->willReturnCallback(
            static function (array $data = []) use (&$calls) {
                $calls[] = $data;

                return (new SanitizedHtmlInterfaceFactory())->create();
            }
        );

        (new EmailHtmlSanitizer($factory, $this->createMock(LoggerInterface::class)))->sanitize(" \t\n");

        $this->assertSame([[]], $calls, 'one plain empty result, nothing parsed');
    }

    public function testRemovedListIsDeduplicatedAndReindexed(): void
    {
        $factory = $this->createMock(SanitizedHtmlInterfaceFactory::class);
        $factory->expects($this->once())->method('create')
            ->with(['html' => '<p>a</p>', 'removed' => ['script', 'style']])
            ->willReturn((new SanitizedHtmlInterfaceFactory())->create());

        (new EmailHtmlSanitizer($factory, $this->createMock(LoggerInterface::class)))
            ->sanitize('<script>1</script><script>2</script><p>a</p><style>3</style>');
    }

    /**
     * @dataProvider controlCharacterSchemeProvider
     */
    public function testControlCharactersInsideASchemeAreIgnored(string $href): void
    {
        $result = $this->sanitizer->sanitize('<a href="' . $href . '">x</a>');

        $this->assertSame('<a>x</a>', $result->getHtml());
        $this->assertSame(['href (unsafe URL)'], $result->getRemoved());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function controlCharacterSchemeProvider(): array
    {
        return [
            'space' => ['java script:alert(1)'],
            'tab' => ["java\tscript:alert(1)"],
            'carriage return' => ["java\rscript:alert(1)"],
            'unit separator' => ["java\x1fscript:alert(1)"],
            'start of heading' => ["java\x01script:alert(1)"],
            'delete' => ["java\x7fscript:alert(1)"],
            'leading control characters' => ["\x01\x02 javascript:alert(1)"],
        ];
    }

    public function testCharactersOutsideTheControlRangeAreNotIgnored(): void
    {
        // "!" (0x21) and "~" (0x7e) sit right next to the stripped ranges; they do not form a scheme
        $result = $this->sanitizer->sanitize('<a href="java!script:x">a</a><a href="java~script:x">b</a>');

        $this->assertSame([], $result->getRemoved());
    }

    public function testResultIsBuiltWithHtmlAndRemoved(): void
    {
        $factory = $this->createMock(SanitizedHtmlInterfaceFactory::class);
        $factory->expects($this->once())->method('create')
            ->with(['html' => '<p>a</p>', 'removed' => ['script']])
            ->willReturn((new SanitizedHtmlInterfaceFactory())->create());

        (new EmailHtmlSanitizer($factory, $this->createMock(LoggerInterface::class)))
            ->sanitize('<p>a</p><script>1</script>');
    }

    public function testFailureIsLoggedWithMessageAndException(): void
    {
        $exception = new \RuntimeException('boom');
        $factory = $this->createMock(SanitizedHtmlInterfaceFactory::class);
        $factory->expects($this->exactly(2))->method('create')->willReturnCallback(
            static function (array $data = []) use ($exception) {
                if ($data !== []) {
                    throw $exception;
                }

                return (new SanitizedHtmlInterfaceFactory())->create();
            }
        );
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error')
            ->with('Invitation block sanitizing failed: boom', ['exception' => $exception]);

        (new EmailHtmlSanitizer($factory, $logger))->sanitize('<p>x</p>');
    }

    public function testLibxmlStateIsRestored(): void
    {
        foreach ([false, true] as $before) {
            libxml_use_internal_errors($before);
            libxml_clear_errors();

            $this->sanitizer->sanitize('<p>unclosed <foo>bar</p></p><baz');

            $this->assertSame($before, libxml_use_internal_errors(), 'flag restored');
            $this->assertSame([], libxml_get_errors(), 'errors cleared');
        }
        libxml_use_internal_errors(false);
    }
}
