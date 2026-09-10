<?php

namespace Tests\Unit;

use App\Mail\Transport\GraphTransport;
use App\Services\Graph\GraphClient;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class GraphTransportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function transport(): GraphTransport
    {
        return new GraphTransport(
            new GraphClient('tenant-1', 'client-1', 'secret-1'),
            'shared@example.com',
        );
    }

    private function email(): Email
    {
        return (new Email)
            ->from('planner@example.com')
            ->to('alice@example.com')
            ->replyTo('planner@example.com')
            ->subject('Your page')
            ->html('<p>Open your link</p>');
    }

    public function test_send_posts_a_graph_sendmail_payload(): void
    {
        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['access_token' => 'tok-abc', 'expires_in' => 3600]),
            'graph.microsoft.com/*' => Http::response(null, 202),
        ]);

        $this->transport()->send($this->email());

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'graph.microsoft.com')) {
                return false;
            }

            $body = $request->data();

            return $request->url() === 'https://graph.microsoft.com/v1.0/users/shared%40example.com/sendMail'
                && $request->hasHeader('Authorization', 'Bearer tok-abc')
                && $body['saveToSentItems'] === true
                && $body['message']['subject'] === 'Your page'
                && $body['message']['body']['contentType'] === 'HTML'
                && $body['message']['toRecipients'][0]['emailAddress']['address'] === 'alice@example.com';
        });
    }

    public function test_send_includes_an_inline_image_attachment(): void
    {
        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['access_token' => 'tok-abc', 'expires_in' => 3600]),
            'graph.microsoft.com/*' => Http::response(null, 202),
        ]);

        $email = $this->email();
    $email->embedFromPath(public_path('images/logo.svg'), 'logo.svg', 'image/svg+xml');
    $contentId = $email->getAttachments()[0]->getContentId();
        $email->html('<img src="cid:'.$contentId.'" alt="Logo">');

        $this->transport()->send($email);

        Http::assertSent(function ($request) use ($contentId) {
            if (! str_contains($request->url(), 'graph.microsoft.com')) {
                return false;
            }

            $attachment = $request->data()['message']['attachments'][0];

            return $attachment['@odata.type'] === '#microsoft.graph.fileAttachment'
                && $attachment['name'] === 'logo.svg'
                && $attachment['contentType'] === 'image/svg+xml'
                && $attachment['isInline'] === true
                && $attachment['contentId'] === $contentId
                && base64_decode($attachment['contentBytes'], true) === file_get_contents(public_path('images/logo.svg'));
        });
    }

    public function test_token_is_reused_across_sends(): void
    {
        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['access_token' => 'tok-abc', 'expires_in' => 3600]),
            'graph.microsoft.com/*' => Http::response(null, 202),
        ]);

        $transport = $this->transport();
        $transport->send($this->email());
        $transport->send($this->email());

        Http::assertSentCount(3); // one token call + two sendMail calls
    }

    public function test_a_graph_error_is_raised_so_the_job_can_retry(): void
    {
        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['access_token' => 'tok-abc', 'expires_in' => 3600]),
            'graph.microsoft.com/*' => Http::response(['error' => 'boom'], 500),
        ]);

        $this->expectException(RequestException::class);

        $this->transport()->send($this->email());
    }
}
