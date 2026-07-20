<?php

namespace App\Support\Media;

use DOMDocument;
use DOMElement;
use DOMXPath;
use enshrined\svgSanitize\Sanitizer;
use InvalidArgumentException;

class SvgUploadSanitizer
{
    /**
     * @var array<int, string>
     */
    private const FORBIDDEN_ELEMENTS = [
        'script',
        'foreignobject',
        'iframe',
        'object',
        'embed',
        'audio',
        'video',
        'canvas',
    ];

    public function sanitize(string $contents): string
    {
        $this->assertSafeDocument($contents);

        $sanitizer = new Sanitizer;
        $sanitizer->removeRemoteReferences(true);
        $sanitizer->removeXMLTag(true);

        $sanitized = $sanitizer->sanitize($contents);

        if (! is_string($sanitized) || trim($sanitized) === '') {
            throw new InvalidArgumentException('The SVG could not be sanitized.');
        }

        $this->assertSafeDocument($sanitized);

        return $sanitized;
    }

    private function assertSafeDocument(string $contents): void
    {
        if (
            preg_match('/<!DOCTYPE|<!ENTITY|<\?xml-stylesheet/i', $contents)
            || preg_match('/data\s*:\s*(?:text\/html|application\/javascript)/i', $contents)
        ) {
            throw new InvalidArgumentException('The SVG contains executable or external declarations.');
        }

        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            $document = new DOMDocument;
            $loaded = $document->loadXML($contents, LIBXML_NONET | LIBXML_NOBLANKS | LIBXML_NOERROR | LIBXML_NOWARNING);
            $errors = libxml_get_errors();
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if (! $loaded || $errors !== []) {
            throw new InvalidArgumentException('The SVG is not valid XML.');
        }

        $root = $document->documentElement;

        if (! $root instanceof DOMElement || mb_strtolower($root->localName) !== 'svg') {
            throw new InvalidArgumentException('The document root is not SVG.');
        }

        if (! in_array($root->namespaceURI, [null, '', 'http://www.w3.org/2000/svg'], true)) {
            throw new InvalidArgumentException('The SVG namespace is not allowed.');
        }

        $xpath = new DOMXPath($document);

        foreach ($xpath->query('//*') ?: [] as $element) {
            if (! $element instanceof DOMElement) {
                continue;
            }

            if (in_array(mb_strtolower($element->localName), self::FORBIDDEN_ELEMENTS, true)) {
                throw new InvalidArgumentException('The SVG contains a forbidden element.');
            }

            foreach ($element->attributes as $attribute) {
                $name = mb_strtolower($attribute->localName);
                $value = trim($attribute->value);

                if (str_starts_with($name, 'on') || $name === 'style') {
                    throw new InvalidArgumentException('The SVG contains an executable attribute.');
                }

                if (in_array($name, ['href', 'src'], true) && $value !== '' && ! str_starts_with($value, '#')) {
                    throw new InvalidArgumentException('The SVG contains an external reference.');
                }

                if (str_contains(mb_strtolower($value), 'url(') && ! preg_match('/^url\(#[A-Za-z_][A-Za-z0-9_.:-]*\)$/', $value)) {
                    throw new InvalidArgumentException('The SVG contains an unsafe URL reference.');
                }
            }
        }
    }
}
