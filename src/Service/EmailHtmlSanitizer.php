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
    private const ROOT_ID = 'pp-sales-email-root';

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

        // A stray closing tag in the input can close the root early; keep everything that follows.
        foreach (iterator_to_array($document->childNodes) as $sibling) {
            if ($sibling !== $root && $sibling->nodeType !== XML_PI_NODE) {
                $root->appendChild($sibling);
            }
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
                $removed[$this->label('%1', $name)] = true;
                $parent->removeChild($child);
                continue;
            }

            $this->cleanChildren($child, $removed);

            if (!in_array($name, $this->allowedTags, true)) {
                $removed[$this->label('%1', $name)] = true;
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
                $pattern = '%1 attribute';
                if (in_array($name, $this->urlAttributes, true) && in_array($name, $this->allowedAttributes, true)) {
                    $pattern = '%1 (unsafe URL)';
                } elseif (in_array($name, $this->allowedAttributes, true)) {
                    $pattern = '%1 (unsafe value)';
                }
                $removed[$this->label($pattern, $name)] = true;
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
        if (preg_match_all('/url\s*\(([^)]*)\)/i', $style, $matches) > 0) {
            foreach ($matches[1] as $url) {
                $url = trim($url, " \t\n\r\"'");
                if (preg_match('/^https?:\/\//i', $url) !== 1 && preg_match('/^[a-z][a-z0-9+.\-]*:/i', $url) === 1) {
                    return false;
                }
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
