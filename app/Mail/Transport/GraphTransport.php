<?php

namespace App\Mail\Transport;

use App\Services\Graph\GraphClient;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\MessageConverter;
use Symfony\Component\Mime\Part\DataPart;

/**
 * Laravel mail transport that delivers through Microsoft Graph's
 * `/users/{sender}/sendMail` from one shared mailbox. Selected in
 * production with MAIL_MAILER=graph; local dev stays on `log`.
 *
 * The job, the ComposedMessage mailable, and the preview pipeline are
 * unchanged — Laravel hands this transport a finished message and it
 * translates the parts Graph needs.
 */
class GraphTransport extends AbstractTransport
{
    public function __construct(
        private GraphClient $client,
        private string $sender,
        private bool $saveToSentItems = true,
    ) {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());

        $this->client->sendMail($this->sender, array_filter([
            'subject' => (string) $email->getSubject(),
            'body' => [
                'contentType' => $email->getHtmlBody() !== null ? 'HTML' : 'Text',
                'content' => (string) ($email->getHtmlBody() ?? $email->getTextBody() ?? ''),
            ],
            'toRecipients' => $this->recipients($email->getTo()),
            'ccRecipients' => $this->recipients($email->getCc()),
            'bccRecipients' => $this->recipients($email->getBcc()),
            'replyTo' => $this->recipients($email->getReplyTo()),
            'attachments' => array_map(fn (DataPart $attachment) => array_filter([
                '@odata.type' => '#microsoft.graph.fileAttachment',
                'name' => $attachment->getName(),
                'contentType' => $attachment->getContentType(),
                'contentBytes' => base64_encode($attachment->getBody()),
                'isInline' => $attachment->hasContentId(),
                'contentId' => $attachment->hasContentId() ? $attachment->getContentId() : null,
            ]), $email->getAttachments()),
        ]), $this->saveToSentItems);
    }

    /** @param  Address[]  $addresses */
    private function recipients(array $addresses): array
    {
        return array_map(fn (Address $address) => [
            'emailAddress' => array_filter([
                'address' => $address->getAddress(),
                'name' => $address->getName() ?: null,
            ]),
        ], $addresses);
    }

    public function __toString(): string
    {
        return 'graph';
    }
}
