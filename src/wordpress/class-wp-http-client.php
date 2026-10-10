<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Internal\WordPress;

use ScottOffen\WpGithubUpdater\Internal\Http\HttpClient;
use ScottOffen\WpGithubUpdater\Internal\Http\HttpResponse;

/**
 * @internal
 */
final class WpHttpClient implements HttpClient
{
    private const GET_TIMEOUT = 15;
    private const DOWNLOAD_TIMEOUT = 300;

    public function get(string $url, array $headers = []): HttpResponse
    {
        return $this->read(\wp_safe_remote_get($url, [
            'headers' => $headers,
            'redirection' => 0,
            'timeout' => self::GET_TIMEOUT,
        ]));
    }

    public function download(string $url, array $headers, string $destination): HttpResponse
    {
        $scratch = $destination . '.part';

        $response = $this->read(\wp_safe_remote_get($url, [
            'headers' => $headers,
            'redirection' => 0,
            'timeout' => self::DOWNLOAD_TIMEOUT,
            'stream' => true,
            'filename' => $scratch,
        ]));

        $succeeded = $response->status >= 200 && $response->status < 300;

        if (!$succeeded || !$this->moveIntoPlace($scratch, $destination)) {
            \wp_delete_file($scratch);

            return $succeeded
                ? HttpResponse::failure('The download could not be written to its destination.')
                : new HttpResponse($response->status, $response->headers, '', $response->error);
        }

        return new HttpResponse($response->status, $response->headers);
    }

    /**
     * @param array<string, mixed>|\WP_Error $response What WordPress returned.
     */
    private function read(mixed $response): HttpResponse
    {
        if (\is_wp_error($response)) {
            return HttpResponse::failure($response->get_error_message());
        }

        $headers = [];
        $raw = \wp_remote_retrieve_headers($response);

        if (is_iterable($raw)) {
            foreach ($raw as $name => $value) {
                $headers[(string) $name] = $value;
            }
        }

        return new HttpResponse(
            (int) \wp_remote_retrieve_response_code($response),
            $headers,
            (string) \wp_remote_retrieve_body($response),
        );
    }


    private function moveIntoPlace(string $scratch, string $destination): bool
    {
        if (@rename($scratch, $destination)) {
            return true;
        }

        if (@copy($scratch, $destination)) {
            \wp_delete_file($scratch);

            return true;
        }

        return false;
    }
}
