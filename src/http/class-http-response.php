<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Internal\Http;

/**
 * The result of an HTTP request: the status, the headers, the body, and an
 * error message.
 *
 * When no response is returned, the status is 0 and the response has an error
 * message.
 *
 * @internal
 */
final class HttpResponse
{
    /** The statuses that the `isRedirect()` method treats as redirects. */
    private const REDIRECTS = [301, 302, 303, 307, 308];

    /**
     * The response headers, with lowercase names. A header that has several
     * values is joined with a comma and a space.
     *
     * @var array<string, string>
     */
    public readonly array $headers;

    /**
     * Creates a response. The names of the headers are converted to lowercase,
     * and a header that has several values is joined with a comma and a space.
     *
     * @param int $status The HTTP status, or 0 when no response is returned.
     * @param array<string, string|list<string>> $headers The response headers.
     * @param string $body The body of the response.
     * @param string $error The error message when the response failed.
     */
    public function __construct(
        public readonly int $status,
        array $headers = [],
        public readonly string $body = '',
        public readonly string $error = '',
    ) {
        $normalized = [];

        foreach ($headers as $name => $value) {
            $normalized[strtolower((string) $name)] = is_array($value)
                ? implode(', ', array_map('strval', $value))
                : (string) $value;
        }

        $this->headers = $normalized;
    }

    /**
     * Indicates whether the status means that no response is returned.
     *
     * @return bool True when the status is 0.
     */
    public function failed(): bool
    {
        return $this->status === 0;
    }

    /**
     * Returns the response for the case where no response is returned.
     *
     * @param string $error The error message.
     *
     * @return self A response with the status 0, no headers, no body, and the
     *     error message.
     */
    public static function failure(string $error): self
    {
        return new self(0, [], '', $error);
    }

    /**
     * Returns the value of a header.
     *
     * @param string $name The name of the header. It is not case sensitive.
     *
     * @return string|null The value, or null when the response has no such
     *     header.
     */
    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    /**
     * Indicates whether the status is a redirect status.
     *
     * @return bool True when the status is 301, 302, 303, 307, or 308.
     */
    public function isRedirect(): bool
    {
        return in_array($this->status, self::REDIRECTS, true);
    }
}
