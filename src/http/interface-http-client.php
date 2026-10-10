<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Internal\Http;

/**
 * @internal
 */
interface HttpClient
{
    /**
     * @param string $url The request URL.
     * @param array<string, string> $headers The request headers.
     */
    public function get(string $url, array $headers = []): HttpResponse;

    /**
     * @param string $url The request URL.
     * @param array<string, string> $headers The request headers.
     * @param string $destination The file path to write the downloaded content to.
     */
    public function download(string $url, array $headers, string $destination): HttpResponse;
}
