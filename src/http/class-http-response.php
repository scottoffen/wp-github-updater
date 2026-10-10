<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Http;

/**
 * An answer to an HTTP request.
 *
 * A request that never got an answer is a response with a status of 0 and a
 * description of what went wrong in `error`.
 */
final class HttpResponse
{
    /** The statuses that tell the caller to ask somewhere else. */
    private const REDIRECTS = [301, 302, 303, 307, 308];

    /**
     * The headers, keyed by lowercase name. A header sent more than once is
     * joined into one value with a comma and a space.
     *
     * @var array<string, string>
     */
    public readonly array $headers;

    /**
     * @param int $status The HTTP status, or 0 when no answer arrived.
     * @param array<string, string|list<string>> $headers The headers, with names in any case.
     * @param string $body The body of the answer.
     * @param string $error What went wrong when the status is 0.
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
     * Makes the response for a request that never got an answer.
     */
    public static function failure(string $error): self
    {
        return new self(0, [], '', $error);
    }

    /**
     * @param string $name The name of the header, in any case.
     *
     * @return string|null The value, or null when the answer has no such header.
     */
    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    /**
     * @return bool True when no answer arrived at all.
     */
    public function failed(): bool
    {
        return $this->status === 0;
    }

    /**
     * @return bool True when the status asks the caller to go somewhere else.
     */
    public function isRedirect(): bool
    {
        return in_array($this->status, self::REDIRECTS, true);
    }
}
