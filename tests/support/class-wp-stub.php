<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Tests\Support;

/**
 * The recorded calls and the configurable results of the WordPress function
 * stubs.
 */
final class WpStub
{
    /** @var array<string, mixed> */
    public static array $options = [];

    /** @var array<string, mixed> */
    public static array $autoload = [];

    /**
     * @var list<array{type: string, hook: string, callback: callable, priority:
     *     int, accepted: int}>
     */
    public static array $hooks = [];

    /** @var list<array{url: string, args: array<string, mixed>}> */
    public static array $requests = [];

    /** @var list<array<string, mixed>|\WP_Error> */
    private static array $responses = [];

    /** @var list<string> */
    public static array $deleted = [];

    /** @var list<string> */
    private static array $temps = [];

    public static bool $tempFails = false;

    /**
     * @var list<array{string, string}> Each translated string with its text
     *     domain.
     */
    public static array $translated = [];

    /** @var list<string> */
    public static array $transientsDeleted = [];

    /** @var list<string> */
    public static array $ksesInputs = [];

    /** @var list<string> Capabilities the stubbed signed-in person has. */
    public static array $capabilities = [];

    /**
     * @var list<array{message: string, title: string, args: array<string,
     *     mixed>}>
     */
    public static array $dies = [];

    public static int $noCacheHeaders = 0;

    /** True to make the `wp_date()` stub return `false`. */
    public static bool $dateFails = false;

    /** The value that the `wp_get_referer()` stub returns. */
    public static string|false $referer = false;

    /** @var list<string> */
    public static array $redirects = [];

    /**
     * @var list<array{parent: string, page: string, menu: string, capability:
     *     string, slug: string, callback: callable}>
     */
    public static array $submenus = [];

    /**
     * The closure that the `wp_kses_post()` stub applies, or null to return the
     * input unchanged.
     */
    public static ?\Closure $kses = null;

    /**
     * Removes the next queued response and returns it.
     *
     * @return array<string, mixed>|\WP_Error The response.
     *
     * @throws \LogicException When no response is queued.
     */
    public static function next(string $url): array|\WP_Error
    {
        if (self::$responses === []) {
            throw new \LogicException('No response was queued for ' . $url);
        }

        return array_shift(self::$responses);
    }

    /**
     * Adds responses to the queue that the `wp_safe_remote_get()` stub returns,
     * in order.
     */
    public static function queue(array|\WP_Error ...$responses): void
    {
        foreach ($responses as $response) {
            self::$responses[] = $response;
        }
    }

    /**
     * Resets every property to its initial value and deletes the temporary
     * files that the `temp()` method created.
     */
    public static function reset(): void
    {
        foreach (self::$temps as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        self::$options = [];
        self::$autoload = [];
        self::$hooks = [];
        self::$requests = [];
        self::$responses = [];
        self::$deleted = [];
        self::$temps = [];
        self::$tempFails = false;
        self::$translated = [];
        self::$transientsDeleted = [];
        self::$ksesInputs = [];
        self::$kses = null;
        self::$capabilities = [];
        self::$dies = [];
        self::$noCacheHeaders = 0;
        self::$dateFails = false;
        self::$referer = false;
        self::$redirects = [];
        self::$submenus = [];
    }

    /**
     * Returns a response array in the format of the `wp_safe_remote_get()`
     * function.
     *
     * @param array<string, string|list<string>> $headers The response headers.
     *
     * @return array<string, mixed> The response array, with the status, the
     *     headers, and the body.
     */
    public static function response(int $status, array $headers = [], string $body = ''): array
    {
        return [
            'headers' => $headers,
            'body' => $body,
            'response' => ['code' => $status, 'message' => ''],
            'cookies' => [],
            'filename' => null,
        ];
    }

    /**
     * Creates a temporary file. The `reset()` method deletes the file.
     *
     * @return string The path of the file.
     */
    public static function temp(): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'wpgu');
        self::$temps[] = $path;

        return $path;
    }
}
