<?php declare(strict_types=1);

namespace PixelPerfect\SalesEmail\Service;

use DOMComment;
use DOMDocument;
use DOMElement;
use DOMNode;
use PixelPerfect\SalesEmail\Api\Data\SanitizedHtmlInterface;
use PixelPerfect\SalesEmail\Api\Service\EmailHtmlSanitizerInterface;
use PixelPerfect\SalesEmail\Api\Data\SanitizedHtmlInterfaceFactory;
use Psr\Log\LoggerInterface;

class EmailHtmlSanitizer implements EmailHtmlSanitizerInterface
{
    /**
     * Control characters and space (0x00-0x20, 0x7f): browsers ignore them inside a URL scheme
     */
    private const IGNORED_IN_URLS = "\x00\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0a\x0b\x0c\x0d\x0e\x0f\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1a\x1b\x1c\x1d\x1e\x1f\x20\x7f";

    /**
     * @param SanitizedHtmlInterfaceFactory $resultFactory
     * @param LoggerInterface $logger
     * @param string[] $allowedTags tags kept; unknown tags are unwrapped (children kept)
     * @param string[] $allowedAttributes
     * @param string[] $droppedTags tags removed together with their content
     * @param string[] $urlAttributes attributes whose value must carry a safe scheme
     * @param string[] $allowedSchemes
     */
    public function __construct(
        private readonly SanitizedHtmlInterfaceFactory $resultFactory,
        private readonly LoggerInterface $logger,
        private readonly array $allowedTags = [
            'table', 'tbody', 'thead', 'tr', 'td', 'th', 'p', 'a', 'strong', 'b', 'em', 'i', 'br', 'span',
            'div', 'h1', 'h2', 'h3', 'ul', 'ol', 'li', 'img', 'hr',
        ],
        private readonly array $allowedAttributes = [
            'href', 'src', 'alt', 'width', 'height', 'style', 'align', 'valign', 'border', 'cellpadding',
            'cellspacing', 'role', 'title', 'bgcolor',
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
        $document->loadHTML(
            sprintf('<?xml encoding="utf-8"?><div>%s</div>', $html),
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $document->encoding = 'UTF-8';

        // The wrapper div is always the document element; the parser never fails on a non-empty string.
        /** @var DOMElement $root */
        $root = $document->documentElement;

        // A stray closing tag in the input can close the root early; keep everything that follows.
        foreach (iterator_to_array($document->childNodes) as $sibling) {
            if ($sibling !== $root && $sibling->nodeType !== XML_PI_NODE) {
                $root->appendChild($sibling);
            }
        }

        $removed = [];
        $this->cleanChildren($root, $removed);
        $removed = array_values(array_unique($removed));

        $output = '';
        foreach ($root->childNodes as $child) {
            $output .= $document->saveHTML($child);
        }

        return $this->resultFactory->create(['html' => trim($output), 'removed' => $removed]);
    }

    /**
     * Clean the children of a node in place
     *
     * @param DOMNode $parent
     * @param string[] $removed
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

            $name = $child->nodeName; // the HTML parser lower-cases tag names
            if (in_array($name, $this->droppedTags, true)) {
                $removed[] = $this->label('%1', $name);
                $parent->removeChild($child);
                continue;
            }

            $this->cleanChildren($child, $removed);

            if (!in_array($name, $this->allowedTags, true)) {
                $removed[] = $this->label('%1', $name);
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
     * @param string[] $removed
     * @return void
     */
    private function cleanAttributes(DOMElement $element, array &$removed): void
    {
        foreach (iterator_to_array($element->attributes) as $attribute) {
            $name = $attribute->nodeName; // the HTML parser lower-cases attribute names
            if (!in_array($name, $this->allowedAttributes, true) || !$this->isSafeValue($name, $attribute->value)) {
                $pattern = '%1 attribute';
                if (in_array($name, $this->urlAttributes, true) && in_array($name, $this->allowedAttributes, true)) {
                    $pattern = '%1 (unsafe URL)';
                } elseif (in_array($name, $this->allowedAttributes, true)) {
                    $pattern = '%1 (unsafe value)';
                }
                $removed[] = $this->label($pattern, $name);
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
            $compact = strtolower(str_replace(
                str_split(self::IGNORED_IN_URLS),
                '',
                html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8')
            ));

            return preg_match('/^([a-z][a-z0-9+.\-]*):/', $compact, $match) !== 1
                || in_array($match[1], $this->allowedSchemes, true);
        }
        if ($name === 'style') {
            return $this->isSafeStyle($value);
        }

        return true;
    }

    /**
     * Reject escapes, comments and script-capable constructs; url() may only point to http(s)
     *
     * @param string $style
     * @return bool
     */
    private function isSafeStyle(string $style): bool
    {
        if (str_contains($style, '\\') || str_contains($style, '/*')) {
            return false;
        }
        if (preg_match('/expression|behavior|javascript|vbscript|-moz-binding|@import/i', $style) === 1) {
            return false;
        }
        preg_match_all('/url\s*\(([^)]*)\)/i', $style, $matches);
        foreach ($matches[1] as $url) {
            $url = trim($url, " \t\n\r\"'");
            if (preg_match('/^https?:\/\//i', $url) !== 1 && preg_match('/^[a-z][a-z0-9+.\-]*:/i', $url) === 1) {
                return false;
            }
        }

        return true;
    }

    /**
     * Translated label for a removed item
     *
     * @param string $pattern
     * @param string $name
     * @return string
     */
    private function label(string $pattern, string $name): string
    {
        return (string)__($pattern, $name);
    }
}
