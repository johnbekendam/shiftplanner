<?php

namespace App\Services;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;

class MarkdownRenderer
{
    private ?MarkdownConverter $converter = null;

    /**
     * Render Markdown and replace :button[Label](target) with the supplied
     * context-specific button markup.
     *
     * @param callable(string, string): string $buttonRenderer
     */
    public function render(string $markdown, callable $buttonRenderer, bool $allowUnsafeLinks = false): string
    {
        [$markdown, $buttons] = $this->extractButtons($markdown, $buttonRenderer);
        [$markdown, $spacers] = $this->extractSpacers($markdown);
        $html = $this->toHtml($markdown, $allowUnsafeLinks);

        foreach ($buttons as $marker => $button) {
            $html = str_replace('<p>'.$marker.'</p>', $button, $html);
            $html = str_replace($marker, $button, $html);
        }

        foreach ($spacers as $marker => $spacer) {
            $html = str_replace('<p>'.$marker.'</p>', $spacer, $html);
            $html = str_replace($marker, $spacer, $html);
        }

        return $html;
    }

    private function toHtml(string $markdown, bool $allowUnsafeLinks): string
    {
        if ($this->converter === null) {
            $environment = new Environment([
                'html_input' => 'allow',
                'allow_unsafe_links' => $allowUnsafeLinks,
            ]);
            $environment->addExtension(new CommonMarkCoreExtension);
            $environment->addExtension(new GithubFlavoredMarkdownExtension);
            $this->converter = new MarkdownConverter($environment);
        }

        return (string) $this->converter->convert($markdown);
    }

    /**
     * Replace each standalone `:---` line with a unique marker, to be
     * swapped for a spacer element after conversion. Each occurrence
     * becomes its own marker, so repeating the marker stacks the space.
     *
     * @return array{string, array<string, string>}
     */
    private function extractSpacers(string $markdown): array
    {
        $spacers = [];

        $markdown = preg_replace_callback('/^:---[ \t]*$/m', function () use (&$spacers): string {
            $marker = 'SHIFTPLANNER_SPACER_'.count($spacers);
            $spacers[$marker] = '<div data-note-spacer aria-hidden="true"></div>';

            return $marker;
        }, $markdown);

        return [$markdown, $spacers];
    }

    /** @return array{string, array<string, string>} */
    private function extractButtons(string $markdown, callable $buttonRenderer): array
    {
        $buttons = [];
        $offset = 0;

        while (($start = strpos($markdown, ':button[', $offset)) !== false) {
            $labelEnd = strpos($markdown, '](', $start + 8);

            if ($labelEnd === false) {
                break;
            }

            $targetStart = $labelEnd + 2;
            $targetEnd = $this->closingParenthesis($markdown, $targetStart);

            if ($targetEnd === null) {
                break;
            }

            $label = substr($markdown, $start + 8, $labelEnd - ($start + 8));
            $target = substr($markdown, $targetStart, $targetEnd - $targetStart);

            if ($label === '' || preg_match('/^\s*(?:javascript|vbscript|data):/i', $target)) {
                $offset = $targetEnd + 1;
                continue;
            }

            $marker = 'SHIFTPLANNER_BUTTON_'.count($buttons);
            $buttons[$marker] = $buttonRenderer($label, $target);
            $markdown = substr_replace($markdown, $marker, $start, $targetEnd + 1 - $start);
            $offset = $start + strlen($marker);
        }

        return [$markdown, $buttons];
    }

    private function closingParenthesis(string $text, int $start): ?int
    {
        $depth = 1;

        for ($index = $start, $length = strlen($text); $index < $length; $index++) {
            if ($text[$index] === '(') {
                $depth++;
            } elseif ($text[$index] === ')' && --$depth === 0) {
                return $index;
            }
        }

        return null;
    }
}
