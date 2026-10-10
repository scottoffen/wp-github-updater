<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Tests\Support;

/**
 * The state behind the WordPress function stubs in `functions-wordpress.php`.
 *
 * A test that touches a stub calls `reset` first, so nothing carries over from
 * one test to the next.
 */
final class WpStub
{
    /** @var array<string, mixed> */
    public static array $options = [];

    /** @var array<string, mixed> */
    public static array $autoload = [];

    /** @var list<array{type: string, hook: string, callback: callable, priority: int, accepted: int}> */
    public static array $hooks = [];

    /** @var list<array{url: string, args: array<string, mixed>}> */
    public static array $requests = [];

    /** @var list<array<string, mixed>|\WP_Error> */
    private static array $answers = [];

    /** @var list<string> */
    public static array $deleted = [];

    /** @var list<string> */
    private static array $temps = [];

    public static bool $tempFails = false;

    /** @var list<array{string, string}> Each translated string with its text domain. */
    public static array $translated = [];

    /** @var list<string> */
    public static array $transientsDeleted = [];

    /** @var list<string> */
    public static array $ksesInputs = [];

    /** @var list<string> Capabilities the stubbed signed-in person has. */
    public static array $capabilities = [];

    /** @var list<array{message: string, title: string, args: array<string, mixed>}> */
    public static array $dies = [];

    public static int $noCacheHeaders = 0;

    /** Makes `wp_date` fail, as it does for a time zone it can't read. */
    public static bool $dateFails = false;

    /** What `wp_get_referer` returns. */
    public static string|false $referer = false;

    /** @var list<string> */
    public static array $redirects = [];

    /** @var list<array{parent: string, page: string, menu: string, capability: string, slug: string, callback: callable}> */
    public static array $submenus = [];

    /** What `wp_kses_post` does to its input. Null returns the input as it is. */
    public static ?\Closure $kses = null;

    /** Clears every stub and removes the temporary files the stubs made. */
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
        self::$answers = [];
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
     * Builds an answer in the shape `wp_safe_remote_get` returns.
     *
     * @param array<string, string|list<string>> $headers
     *
     * @return array<string, mixed>
     */
    public static function answer(int $status, array $headers = [], string $body = ''): array
    {
        return [
            'headers' => $headers,
            'body' => $body,
            'response' => ['code' => $status, 'message' => ''],
            'cookies' => [],
            'filename' => null,
        ];
    }

    /** Adds answers to the end of the queue that `wp_safe_remote_get` draws from. */
    public static function queue(array|\WP_Error ...$answers): void
    {
        foreach ($answers as $answer) {
            self::$answers[] = $answer;
        }
    }

    /** @return array<string, mixed>|\WP_Error */
    public static function next(string $url): array|\WP_Error
    {
        if (self::$answers === []) {
            throw new \LogicException('No answer was queued for ' . $url);
        }

        return array_shift(self::$answers);
    }

    /** Makes a real temporary file and remembers it, so `reset` can remove it. */
    public static function temp(): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'wpgu');
        self::$temps[] = $path;

        return $path;
    }
}
