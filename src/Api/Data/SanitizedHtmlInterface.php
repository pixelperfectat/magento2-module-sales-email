<?php declare(strict_types=1);

namespace PixelPerfect\SalesEmail\Api\Data;

interface SanitizedHtmlInterface
{
    /**
     * Email-safe HTML
     *
     * @return string
     */
    public function getHtml(): string;

    /**
     * Distinct, translated descriptions of what was removed, e.g. "script", "onclick attribute", "href (unsafe URL)"
     *
     * @return string[]
     */
    public function getRemoved(): array;
}
