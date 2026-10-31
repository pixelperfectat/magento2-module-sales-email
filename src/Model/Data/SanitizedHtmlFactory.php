<?php declare(strict_types=1);

namespace PixelPerfect\SalesEmail\Model\Data;

use PixelPerfect\SalesEmail\Api\Data\SanitizedHtmlInterface;

/**
 * Hand-written so unit tests need no generated code.
 */
class SanitizedHtmlFactory
{
    /**
     * @param array{html?: string, removed?: string[]} $data
     * @return SanitizedHtmlInterface
     */
    public function create(array $data = []): SanitizedHtmlInterface
    {
        return new SanitizedHtml($data['html'] ?? '', $data['removed'] ?? []);
    }
}
