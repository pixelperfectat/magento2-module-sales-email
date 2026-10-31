<?php declare(strict_types=1);

namespace PixelPerfect\SalesEmail\Model\Data;

use PixelPerfect\SalesEmail\Api\Data\SanitizedHtmlInterface;

readonly class SanitizedHtml implements SanitizedHtmlInterface
{
    /**
     * @param string $html
     * @param string[] $removed
     */
    public function __construct(
        private string $html = '',
        private array $removed = []
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getHtml(): string
    {
        return $this->html;
    }

    /**
     * @inheritDoc
     */
    public function getRemoved(): array
    {
        return $this->removed;
    }
}
