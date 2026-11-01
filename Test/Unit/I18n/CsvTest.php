<?php

declare(strict_types=1);

namespace PixelPerfect\SalesEmail\Test\Unit\I18n;

use PHPUnit\Framework\TestCase;

class CsvTest extends TestCase
{
    private const I18N = __DIR__ . '/../../../src/i18n';

    /**
     * @param string $file
     * @return string[]
     */
    private function keys(string $file): array
    {
        $keys = [];
        $handle = fopen($file, 'rb');
        while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            if (isset($row[0]) && $row[0] !== '') {
                $keys[] = $row[0];
            }
        }
        fclose($handle);

        return $keys;
    }

    /**
     * Module csv files join the global dictionary: a key core already owns would change its translation shop-wide
     */
    public function testNoKeyIsOwnedByCore(): void
    {
        $coreFiles = glob(__DIR__ . '/../../../vendor/*/module-*/i18n/en_US.csv') ?: [];
        if ($coreFiles === []) {
            $this->markTestSkipped('Core module translations are not installed.');
        }
        $core = [];
        foreach ($coreFiles as $file) {
            $core += array_flip($this->keys($file));
        }

        foreach (['en_US', 'de_DE'] as $locale) {
            $own = $this->keys(self::I18N . '/' . $locale . '.csv');
            $this->assertSame([], array_values(array_filter($own, static fn (string $k): bool => isset($core[$k]))), $locale);
        }
    }

    public function testBothLocalesHaveTheSameKeys(): void
    {
        $en = $this->keys(self::I18N . '/en_US.csv');
        $de = $this->keys(self::I18N . '/de_DE.csv');
        sort($en);
        sort($de);

        $this->assertSame($en, $de);
    }
}
