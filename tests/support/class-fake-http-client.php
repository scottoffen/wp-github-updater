<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Tests\Support;

use LogicException;
use ScottOffen\WpGithubUpdater\Internal\Http\HttpClient;
use ScottOffen\WpGithubUpdater\Internal\Http\HttpResponse;

/**
 * A fake `HttpClient` for returning queued responses and for recording the
 * requests.
 */
final class FakeHttpClient implements HttpClient
{
    /** @var list<HttpResponse> */
    private array $queue = [];

    /**
     * @var list<array{method: string, url: string, headers: array<string,
     *     string>, destination: string|null}>
     */
    private array $requests = [];

    /**
     * @param FakeFiles $files The fake files that the `download()` method puts
     *     the body in.
     */
    public function __construct(private readonly FakeFiles $files)
    {
    }

    /**
     * Records the request and returns the next queued response with an empty
     * body. When the status is 200 to 299, the body is put in the fake files at
     * `$destination`.
     *
     * @throws LogicException When no response is queued.
     */
    public function download(string $url, array $headers, string $destination): HttpResponse
    {
        $this->requests[] = ['method' => 'GET', 'url' => $url, 'headers' => $headers, 'destination' => $destination];

        $response = $this->next($url);

        if ($response->status >= 200 && $response->status < 300) {
            $this->files->put($destination, $response->body);
        }

        return new HttpResponse($response->status, $response->headers, '', $response->error);
    }

    /**
     * Records the request and returns the next queued response.
     *
     * @throws LogicException When no response is queued.
     */
    public function get(string $url, array $headers = []): HttpResponse
    {
        $this->requests[] = ['method' => 'GET', 'url' => $url, 'headers' => $headers, 'destination' => null];

        return $this->next($url);
    }

    /**
     * Adds responses to the queue.
     *
     * @return self The fake.
     */
    public function queue(HttpResponse ...$responses): self
    {
        foreach ($responses as $response) {
            $this->queue[] = $response;
        }

        return $this;
    }

    /**
     * Returns the number of queued responses that haven't been returned.
     *
     * @return int The number of responses.
     */
    public function remaining(): int
    {
        return count($this->queue);
    }

    /**
     * Returns the recorded requests.
     *
     * @return list<array{method: string, url: string, headers: array<string,
     *     string>, destination: string|null}> The requests, in the order of the
     *     calls.
     */
    public function requests(): array
    {
        return $this->requests;
    }

    private function next(string $url): HttpResponse
    {
        if ($this->queue === []) {
            throw new LogicException('No response was queued for GET ' . $url);
        }

        return array_shift($this->queue);
    }
}
