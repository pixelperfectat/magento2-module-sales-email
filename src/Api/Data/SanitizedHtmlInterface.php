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
     * Distinct names of what was removed: tag names, attributes prefixed with "@" (e.g. "script", "@onclick")
     *
     * @return string[]
     */
    public function getRemoved(): array;
}
