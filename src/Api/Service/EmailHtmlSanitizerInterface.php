<?php declare(strict_types=1);

namespace PixelPerfect\SalesEmail\Api\Service;

use PixelPerfect\SalesEmail\Api\Data\SanitizedHtmlInterface;

interface EmailHtmlSanitizerInterface
{
    /**
     * Reduce an HTML fragment to markup that renders in email clients; never throws
     *
     * @param string $html
     * @return SanitizedHtmlInterface
     */
    public function sanitize(string $html): SanitizedHtmlInterface;
}
