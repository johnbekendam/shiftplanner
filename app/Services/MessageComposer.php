<?php

namespace App\Services;

use App\Mail\ComposedMessage;
class MessageComposer
{
    public function __construct(private ?MarkdownRenderer $markdown = null) {}

    public function render(string $subject, string $body): array
    {
        return ['subject' => $subject, 'body_html' => ($this->markdown ??= new MarkdownRenderer)->render(
            $body,
            fn (string $label, string $url): string => view('emails.components.button', [
                'url' => $url,
                'label' => $label,
                'colors' => (new ThemeTokens)->emailColors(),
            ])->render(),
        )];
    }

    /**
     * Wraps the rendered body in the full branded email layout, so preview and stored/sent
     * content are always the same HTML document. Use this variant whenever the HTML will be
     * previewed or persisted — use render() directly only when you need the bare fragment.
     */
    public function renderForRecipient(string $subject, string $body): array
    {
        $rendered = $this->render($subject, $body);
        $html = (new ComposedMessage(
            $rendered['subject'],
            $rendered['body_html'],
            logoSrc: ComposedMessage::browserLogoUrl(),
        ))->render();

        return ['subject' => $rendered['subject'], 'html' => $html];
    }

}
