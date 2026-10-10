<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Internal\Http;

/**
 * An HTTP client for sending GET requests and returning the responses.
 *
 * An implementation must not follow a redirect. It returns the response with
 * the redirect status as it is.
 *
 * @internal
 */
interface HttpClient
{
    /**
     * Sends a GET request and writes the body of a successful response to a
     * file.
     *
     * The file is written only when the status is in the range 200 to 299.
     *
     * @param string $url The request URL.
     * @param array<string, string> $headers The request headers.
     * @param string $destination The file path to write the downloaded
     *     content to.
     *
     * @return HttpResponse The status and the headers of the response. The body
     *     goes to the file and not into the returned response, so the `body` is
     *     empty. When no response is returned, the status is 0 and the response
     *     has an error message.
     */
    public function download(string $url, array $headers, string $destination): HttpResponse;

    /**
     * Sends a GET request and returns the response.
     *
     * @param string $url The request URL.
     * @param array<string, string> $headers The request headers.
     *
     * @return HttpResponse The response. When no response is returned, the
     *     status is 0 and the response has an error message.
     */
    public function get(string $url, array $headers = []): HttpResponse;
}
