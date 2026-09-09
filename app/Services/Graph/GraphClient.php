<?php

namespace App\Services\Graph;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Thin Microsoft Graph client for outbound mail from a shared mailbox.
 *
 * Auth is the client-credentials flow (app permission `Mail.Send` consented
 * for one mailbox); the token is cached for most of its lifetime. Only
 * `sendMail` is needed, so there is no Graph SDK dependency.
 *
 * Inert until GRAPH_* is set and MAIL_MAILER=graph — see
 * doc/features/mailbox/spec.md.
 */
class GraphClient
{
    private const TOKEN_CACHE_KEY = 'graph.access_token';

    public function __construct(
        private string $tenantId,
        private string $clientId,
        private string $clientSecret,
    ) {}

    /** POST a Graph `message` resource to `/users/{sender}/sendMail`. */
    public function sendMail(string $sender, array $message, bool $saveToSentItems = true): void
    {
        Http::withToken($this->token())
            ->acceptJson()
            ->post(
                'https://graph.microsoft.com/v1.0/users/'.rawurlencode($sender).'/sendMail',
                ['message' => $message, 'saveToSentItems' => $saveToSentItems],
            )
            ->throw();
    }

    private function token(): string
    {
        $cached = Cache::get(self::TOKEN_CACHE_KEY);
        if (is_string($cached)) {
            return $cached;
        }

        $response = Http::asForm()
            ->post("https://login.microsoftonline.com/{$this->tenantId}/oauth2/v2.0/token", [
                'grant_type' => 'client_credentials',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'scope' => 'https://graph.microsoft.com/.default',
            ])
            ->throw();

        $token = (string) $response->json('access_token');
        // Keep it for the response lifetime minus a 5-minute safety margin.
        $ttl = max(60, (int) $response->json('expires_in', 3600) - 300);
        Cache::put(self::TOKEN_CACHE_KEY, $token, $ttl);

        return $token;
    }
}
