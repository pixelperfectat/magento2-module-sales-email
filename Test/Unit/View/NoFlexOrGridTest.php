<?php

declare(strict_types=1);

namespace PixelPerfect\SalesEmail\Test\Unit\View;

use PHPUnit\Framework\TestCase;

class NoFlexOrGridTest extends TestCase
{
    private const VIEW = __DIR__ . '/../../../src/view/frontend';

    /**
     * @return array<string, array{string}>
     */
    public static function emailFiles(): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::VIEW));
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getFilename()[0] !== '.' && preg_match('~\.(css|html|phtml|xml)$~', $file->getFilename())) {
                $files[substr($file->getPathname(), strlen(self::VIEW) + 1)] = [$file->getPathname()];
            }
        }

        return $files;
    }

    /**
     * @dataProvider emailFiles
     * @param string $path
     */
    public function testRenderedHtmlHasNoFlexOrGrid(string $path): void
    {
        $content = (string)file_get_contents($path);

        $this->assertDoesNotMatchRegularExpression('~display\s*:\s*(inline-)?(flex|grid)~i', $content);
        $this->assertDoesNotMatchRegularExpression('~\b(flex-|grid-|justify-content|align-items|gap\s*:)~i', $content);
        $this->assertDoesNotMatchRegularExpression('~var\(--~', $content);
        $this->assertDoesNotMatchRegularExpression('~<(svg|script)\b~i', $content);
    }

    public function testStylesheetIsPresentAndCarriesThePalette(): void
    {
        $css = (string)file_get_contents(self::VIEW . '/web/css/pp-sales-email.css');

        foreach (['#2b2b2b', '#6b6b6b', '#e3e3e3', '#f6f6f4', '#3a5a40'] as $colour) {
            $this->assertStringContainsString($colour, $css);
        }
    }
}
