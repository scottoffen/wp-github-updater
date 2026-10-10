<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Http;

/**
 * Sends the GET requests the library needs.
 *
 * An implementation never follows a redirect. A 302 comes back as a 302, with
 * its `Location` header, and the caller decides where to go next. That is what
 * keeps the token off the storage host, whatever the implementation does by
 * default.
 *
 * An implementation doesn't throw when a request gets no answer. It returns a
 * response whose status is 0.
 */
interface HttpClient
{
    /**
     * Sends a GET request.
     *
     * @param string $url The address to request.
     * @param array<string, string> $headers The headers to send.
     */
    public function get(string $url, array $headers = []): HttpResponse;

    /**
     * Sends a GET request and writes the body to a file, for answers too big
     * to hold in memory.
     *
     * Only a 2xx answer writes to the file. Any other answer leaves it as it
     * was. The response that comes back has the status and headers and an
     * empty body.
     *
     * @param string $url The address to request.
     * @param array<string, string> $headers The headers to send.
     * @param string $destination The path of an existing file to write to.
     */
    public function download(string $url, array $headers, string $destination): HttpResponse;
}
