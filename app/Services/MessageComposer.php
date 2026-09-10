<?php

namespace App\Services;

use App\Mail\ComposedMessage;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\MarkdownConverter;

/**
 * Converts a message's Markdown body into HTML. Placeholder resolution
 * (:name, :link) happens before this, in App\Services\PersonalLinkMessage —
 * see doc/features/mailbox/spec.md.
 */
class MessageComposer
{
    private ?MarkdownConverter $converter = null;

    public function render(string $subject, string $body): array
    {
        return ['subject' => $subject, 'body_html' => $this->toHtml($body)];
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

    private function toHtml(string $markdown): string
    {
        if ($this->converter === null) {
            $env = new Environment(['html_input' => 'allow', 'allow_unsafe_links' => false]);
            $env->addExtension(new CommonMarkCoreExtension);
            $this->converter = new MarkdownConverter($env);
        }

        return (string) $this->converter->convert($markdown);
    }
}
