<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Internal\WordPress;

use ScottOffen\WpGithubUpdater\Internal\Http\HttpClient;
use ScottOffen\WpGithubUpdater\Internal\Http\HttpResponse;

/**
 * The implementation of the `HttpClient` interface for the
 * `wp_safe_remote_get()` function.
 *
 * @internal
 */
final class WpHttpClient implements HttpClient
{
    /** The timeout of a `get()` request, in seconds. */
    private const GET_TIMEOUT = 15;

    /** The timeout of a `download()` request, in seconds. */
    private const DOWNLOAD_TIMEOUT = 300;

    /**
     * Streams the body of the response to the file `$destination` followed by
     * `.part`, and then moves the file to `$destination` when the status is 200
     * to 299.
     *
     * The request uses the `wp_safe_remote_get()` function with `redirection`
     * set to 0 and the timeout set by `DOWNLOAD_TIMEOUT`. The method moves the
     * file with the `rename()` function, or with the `copy()` function when the
     * rename fails. The `.part` file is deleted when the status is outside 200
     * to 299 or the file can't be moved.
     *
     * @return HttpResponse The status and the headers of the response, with an
     *     empty body. When no response is returned, the status is 0 and the
     *     response has an error message. When the file can't be moved, the
     *     status is 0 and the message is "The download could not be written to
     *     its destination."
     */
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
     * Sends the request with the `wp_safe_remote_get()` function.
     *
     * The `redirection` argument is 0, so a redirect isn't followed, and the
     * timeout is set by `GET_TIMEOUT`.
     *
     * @return HttpResponse The response returned by the `read()` method.
     */
    public function get(string $url, array $headers = []): HttpResponse
    {
        return $this->read(\wp_safe_remote_get($url, [
            'headers' => $headers,
            'redirection' => 0,
            'timeout' => self::GET_TIMEOUT,
        ]));
    }

    /**
     * Converts the result of the `wp_safe_remote_get()` function to an
     * `HttpResponse`.
     *
     * @param array<string, mixed>|\WP_Error $response The result of the
     *     `wp_safe_remote_get()` function.
     *
     * @return HttpResponse When `$response` is a `WP_Error` object, the result
     *     of the `HttpResponse::failure()` method with the error message.
     *     Otherwise, the status, the headers, and the body of the response.
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
     * Moves a file to a destination.
     *
     * @return bool True when the `rename()` function succeeds, or when the
     *     `copy()` function succeeds after the rename fails, in which case the
     *     original file is deleted.
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
