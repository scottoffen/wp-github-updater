<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Tests\Support;

use LogicException;
use ScottOffen\WpGithubUpdater\Http\HttpClient;
use ScottOffen\WpGithubUpdater\Http\HttpResponse;

/**
 * An HTTP client that answers from a queue and logs every request.
 *
 * A test queues the responses it wants, in the order the code under test will
 * ask for them. Asking when the queue is empty fails the test with the address
 * that was requested. Like the real client, it never follows a redirect.
 *
 * A download writes the queued body into the fake file system when the status
 * is 2xx, and returns the response with an empty body.
 */
final class FakeHttpClient implements HttpClient
{
    /** @var list<HttpResponse> */
    private array $queue = [];

    /** @var list<array{method: string, url: string, headers: array<string, string>, destination: string|null}> */
    private array $requests = [];

    public function __construct(private readonly FakeFiles $files)
    {
    }

    /** Adds responses to the end of the queue. */
    public function queue(HttpResponse ...$responses): self
    {
        foreach ($responses as $response) {
            $this->queue[] = $response;
        }

        return $this;
    }

    public function get(string $url, array $headers = []): HttpResponse
    {
        $this->requests[] = ['method' => 'GET', 'url' => $url, 'headers' => $headers, 'destination' => null];

        return $this->next($url);
    }

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
     * Every request made so far, oldest first.
     *
     * @return list<array{method: string, url: string, headers: array<string, string>, destination: string|null}>
     */
    public function requests(): array
    {
        return $this->requests;
    }

    /** How many queued responses are still waiting to be used. */
    public function remaining(): int
    {
        return count($this->queue);
    }

    private function next(string $url): HttpResponse
    {
        if ($this->queue === []) {
            throw new LogicException('No response was queued for GET ' . $url);
        }

        return array_shift($this->queue);
    }
}
