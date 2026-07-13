<?php

namespace App\Support\Importer\SpotifyLinks;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

class SpotifyHtmlToMarkdown
{
    public function convert(?string $html): string
    {
        if (blank($html)) {
            return '';
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?><body>'.$html.'</body>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $body = $document->getElementsByTagName('body')->item(0);
        $markdown = $body instanceof DOMNode ? $this->children($body) : strip_tags($html);

        return $this->normalizePlainText($markdown);
    }

    public function normalizePlainText(?string $text): string
    {
        if (blank($text)) {
            return '';
        }

        $text = html_entity_decode((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\xc2\xa0", ' ', $text);
        $text = preg_replace("/\r\n?/", "\n", $text) ?? $text;
        $text = preg_replace("/[ \t]+\n/u", "\n", $text) ?? $text;
        $text = preg_replace("/\n{3,}/u", "\n\n", $text) ?? $text;

        return trim($text);
    }

    private function children(DOMNode $node): string
    {
        $content = '';

        foreach ($node->childNodes as $child) {
            $content .= $this->node($child);
        }

        return $content;
    }

    private function node(DOMNode $node): string
    {
        if ($node instanceof DOMText) {
            return $node->wholeText;
        }

        if (! $node instanceof DOMElement) {
            return $this->children($node);
        }

        $name = mb_strtolower($node->nodeName);

        return match ($name) {
            'br' => "\n",
            'p', 'div' => trim($this->children($node))."\n\n",
            'a' => $this->link($node),
            'ul' => $this->list($node, false)."\n",
            'ol' => $this->list($node, true)."\n",
            'strong', 'b' => '**'.trim($this->children($node)).'**',
            'em', 'i' => '*'.trim($this->children($node)).'*',
            default => $this->children($node),
        };
    }

    private function link(DOMElement $node): string
    {
        $text = trim($this->children($node));
        $href = trim($node->getAttribute('href'));

        if ($href === '' || ! str_starts_with($href, 'https://')) {
            return $text;
        }

        $text = str_replace([']', "\n"], ['\]', ' '], $text ?: $href);

        return "[{$text}]({$href})";
    }

    private function list(DOMElement $node, bool $ordered): string
    {
        $lines = [];
        $index = 1;

        foreach ($node->childNodes as $child) {
            if (! $child instanceof DOMElement || mb_strtolower($child->nodeName) !== 'li') {
                continue;
            }

            $prefix = $ordered ? "{$index}. " : '- ';
            $lines[] = $prefix.trim($this->children($child));
            $index++;
        }

        return implode("\n", $lines);
    }
}
