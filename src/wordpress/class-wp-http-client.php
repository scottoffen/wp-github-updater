<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\WordPress;

use ScottOffen\WpGithubUpdater\Http\HttpClient;
use ScottOffen\WpGithubUpdater\Http\HttpResponse;

/**
 * Sends requests with the WordPress HTTP API.
 *
 * Every request goes through `wp_safe_remote_get`, which refuses addresses
 * that point at the server's own network, and every request turns redirects
 * off, so a redirect comes back to the caller as a redirect.
 *
 * A download is streamed to a scratch file beside the destination. WordPress
 * writes the body of any answer to the file it streams to, including a
 * redirect or an error, so the scratch file moves to the destination only for
 * a 2xx answer. Any other answer leaves the destination as it was.
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

        $answered = $response->status >= 200 && $response->status < 300;

        if (!$answered || !$this->moveIntoPlace($scratch, $destination)) {
            \wp_delete_file($scratch);

            return $answered
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

    /**
     * Moves the scratch file onto the destination, replacing what is there.
     * A rename can fail across file systems, so a copy is the fallback. Both
     * report failure through their return value, so their warnings are
     * silenced.
     */
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
