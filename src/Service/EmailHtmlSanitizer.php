<?php declare(strict_types=1);

namespace PixelPerfect\SalesEmail\Service;

use DOMComment;
use DOMDocument;
use DOMElement;
use DOMNode;
use PixelPerfect\SalesEmail\Api\Data\SanitizedHtmlInterface;
use PixelPerfect\SalesEmail\Api\Service\EmailHtmlSanitizerInterface;
use PixelPerfect\SalesEmail\Model\Data\SanitizedHtmlFactory;
use Psr\Log\LoggerInterface;

class EmailHtmlSanitizer implements EmailHtmlSanitizerInterface
{
    private const ROOT_ID = 'pp-sales-email-root';

    /**
     * @param SanitizedHtmlFactory $resultFactory
     * @param LoggerInterface $logger
     * @param string[] $allowedTags tags kept; unknown tags are unwrapped (children kept)
     * @param string[] $allowedAttributes
     * @param string[] $droppedTags tags removed together with their content
     * @param string[] $urlAttributes attributes whose value must carry a safe scheme
     * @param string[] $allowedSchemes
     */
    public function __construct(
        private readonly SanitizedHtmlFactory $resultFactory,
        private readonly LoggerInterface $logger,
        private readonly array $allowedTags = [
            'table', 'tbody', 'thead', 'tr', 'td', 'th', 'p', 'a', 'strong', 'b', 'em', 'i', 'br', 'span',
            'div', 'h1', 'h2', 'h3', 'ul', 'ol', 'li', 'img', 'hr',
        ],
        private readonly array $allowedAttributes = [
            'href', 'src', 'alt', 'width', 'height', 'style', 'align', 'valign', 'border', 'cellpadding',
            'cellspacing', 'role', 'title',
        ],
        private readonly array $droppedTags = [
            'script', 'style', 'link', 'svg', 'form', 'input', 'button', 'iframe', 'object', 'embed', 'meta',
            'base', 'textarea', 'select', 'noscript', 'template', 'math',
        ],
        private readonly array $urlAttributes = ['href', 'src'],
        private readonly array $allowedSchemes = ['http', 'https', 'mailto', 'tel']
    ) {
    }

    /**
     * @inheritDoc
     */
    public function sanitize(string $html): SanitizedHtmlInterface
    {
        try {
            if (trim($html) === '') {
                return $this->resultFactory->create();
            }

            return $this->doSanitize($html);
        } catch (\Throwable $e) {
            $this->logger->error('Invitation block sanitizing failed: ' . $e->getMessage(), ['exception' => $e]);

            return $this->resultFactory->create();
        }
    }

    /**
     * Parse, clean and serialise the fragment
     *
     * @param string $html
     * @return SanitizedHtmlInterface
     */
    private function doSanitize(string $html): SanitizedHtmlInterface
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML(
                '<?xml encoding="utf-8"?><div id="' . self::ROOT_ID . '">' . $html . '</div>',
                LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
            );
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $document->encoding = 'UTF-8';

        $root = null;
        foreach ($document->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $root = $child;
                break;
            }
        }
        if ($root === null) {
            return $this->resultFactory->create();
        }

        $removed = [];
        $this->cleanChildren($root, $removed);

        $output = '';
        foreach ($root->childNodes as $child) {
            $output .= $document->saveHTML($child);
        }

        return $this->resultFactory->create(['html' => trim($output), 'removed' => array_keys($removed)]);
    }

    /**
     * Clean the children of a node in place
     *
     * @param DOMNode $parent
     * @param array<string, true> $removed
     * @return void
     */
    private function cleanChildren(DOMNode $parent, array &$removed): void
    {
        foreach (iterator_to_array($parent->childNodes) as $child) {
            if ($child instanceof DOMComment || $child->nodeType === XML_PI_NODE) {
                $parent->removeChild($child);
                continue;
            }
            if (!$child instanceof DOMElement) {
                continue;
            }

            $name = strtolower($child->nodeName);
            if (in_array($name, $this->droppedTags, true)) {
                $removed[$name] = true;
                $parent->removeChild($child);
                continue;
            }

            $this->cleanChildren($child, $removed);

            if (!in_array($name, $this->allowedTags, true)) {
                $removed[$name] = true;
                while ($child->firstChild !== null) {
                    $parent->insertBefore($child->firstChild, $child);
                }
                $parent->removeChild($child);
                continue;
            }

            $this->cleanAttributes($child, $removed);
        }
    }

    /**
     * Drop attributes outside the allow-list and unsafe URL or style values
     *
     * @param DOMElement $element
     * @param array<string, true> $removed
     * @return void
     */
    private function cleanAttributes(DOMElement $element, array &$removed): void
    {
        foreach (iterator_to_array($element->attributes) as $attribute) {
            $name = strtolower($attribute->nodeName);
            if (!in_array($name, $this->allowedAttributes, true) || !$this->isSafeValue($name, $attribute->value)) {
                $removed['@' . $name] = true;
                $element->removeAttribute($attribute->nodeName);
            }
        }
    }

    /**
     * Whether an allowed attribute carries a harmless value
     *
     * @param string $name
     * @param string $value
     * @return bool
     */
    private function isSafeValue(string $name, string $value): bool
    {
        if (in_array($name, $this->urlAttributes, true)) {
            $compact = strtolower((string)preg_replace('/[\x00-\x20\x7f]+/', '', $value));
            $compact = html_entity_decode($compact, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $compact = strtolower((string)preg_replace('/[\x00-\x20\x7f]+/', '', $compact));
            if (preg_match('/^([a-z][a-z0-9+.\-]*):/', $compact, $match) === 1) {
                return in_array($match[1], $this->allowedSchemes, true);
            }

            return true;
        }
        if ($name === 'style') {
            return preg_match('/expression\s*\(|javascript:|vbscript:|-moz-binding|behavior\s*:|@import/i', $value) !== 1;
        }

        return true;
    }
}
