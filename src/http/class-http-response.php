<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Internal\Http;

/**
 * @internal
 */
final class HttpResponse
{

    private const REDIRECTS = [301, 302, 303, 307, 308];

    /**
     * @var array<string, string>
     */
    public readonly array $headers;

    /**
     * @param int $status The HTTP status, or 0 when no response arrived.
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


    public static function failure(string $error): self
    {
        return new self(0, [], '', $error);
    }

    /**
     * @param string $name The name of the header, in any case.
     *
     * @return string|null The value, or null when the response has no such header.
     */
    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    /**
     * @return bool True when no response arrived at all.
     */
    public function failed(): bool
    {
        return $this->status === 0;
    }

    /**
     * @return bool True when the status means the caller should request another address.
     */
    public function isRedirect(): bool
    {
        return in_array($this->status, self::REDIRECTS, true);
    }
}
