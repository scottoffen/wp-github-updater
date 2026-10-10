<?php

declare(strict_types=1);

/**
 * Stand-ins for the WordPress functions the library calls, so the WordPress
 * wrappers run in the unit tests without WordPress. They keep their state in
 * `WpStub`. The library itself defines no global functions, only these tests do.
 */

namespace {
    use ScottOffen\WpGithubUpdater\Tests\Support\WpStub;

    if (!class_exists('WP_Error', false)) {
        class WP_Error
        {
            public function __construct(private string $code = '', private string $message = '')
            {
            }

            public function get_error_code(): string
            {
                return $this->code;
            }

            public function get_error_message(): string
            {
                return $this->message;
            }
        }
    }

    function is_wp_error(mixed $thing): bool
    {
        return $thing instanceof WP_Error;
    }

    function get_option(string $option, mixed $default = false): mixed
    {
        return array_key_exists($option, WpStub::$options) ? WpStub::$options[$option] : $default;
    }

    function update_option(string $option, mixed $value, mixed $autoload = null): bool
    {
        WpStub::$options[$option] = $value;
        WpStub::$autoload[$option] = $autoload;

        return true;
    }

    function delete_option(string $option): bool
    {
        unset(WpStub::$options[$option], WpStub::$autoload[$option]);

        return true;
    }

    function add_filter(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): bool
    {
        WpStub::$hooks[] = ['type' => 'filter', 'hook' => $hook, 'callback' => $callback, 'priority' => $priority, 'accepted' => $acceptedArgs];

        return true;
    }

    function add_action(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): bool
    {
        WpStub::$hooks[] = ['type' => 'action', 'hook' => $hook, 'callback' => $callback, 'priority' => $priority, 'accepted' => $acceptedArgs];

        return true;
    }

    function delete_site_transient(string $transient): bool
    {
        WpStub::$transientsDeleted[] = $transient;

        return true;
    }

    function wp_kses_post(string $data): string
    {
        WpStub::$ksesInputs[] = $data;

        return WpStub::$kses === null ? $data : (WpStub::$kses)($data);
    }

    function __(string $text, string $domain = 'default'): string
    {
        WpStub::$translated[] = [$text, $domain];

        return $text;
    }

    function admin_url(string $path = ''): string
    {
        return 'https://example.test/wp-admin/' . ltrim($path, '/');
    }

    function current_user_can(string $capability): bool
    {
        return in_array($capability, WpStub::$capabilities, true);
    }

    function nocache_headers(): void
    {
        WpStub::$noCacheHeaders++;
    }

    /** @param array<string, mixed> $args */
    function wp_die(string $message = '', string $title = '', array $args = []): void
    {
        WpStub::$dies[] = ['message' => $message, 'title' => $title, 'args' => $args];
    }

    function wp_unslash(mixed $value): mixed
    {
        return is_string($value) ? stripslashes($value) : $value;
    }

    function wp_nonce_field(string $action = '-1', string $name = '_wpnonce', bool $referer = true, bool $display = true): string
    {
        $field = '<input type="hidden" id="' . $name . '" name="' . $name . '" value="nonce-' . $action . '" />';

        if ($referer) {
            $field .= '<input type="hidden" name="_wp_http_referer" value="/wp-admin/options-general.php?page=x" />';
        }

        if ($display) {
            echo $field;
        }

        return $field;
    }

    function wp_verify_nonce(string $nonce, string $action = '-1'): int|false
    {
        return $nonce === 'nonce-' . $action ? 1 : false;
    }

    function wp_get_referer(): string|false
    {
        return WpStub::$referer;
    }

    function wp_safe_redirect(string $location, int $status = 302): bool
    {
        WpStub::$redirects[] = $location;

        return true;
    }

    function add_query_arg(string $key, string $value, string $url): string
    {
        $parts = explode('#', $url, 2);
        $base = $parts[0];
        $fragment = isset($parts[1]) ? '#' . $parts[1] : '';
        $query = '';

        if (str_contains($base, '?')) {
            [$base, $query] = explode('?', $base, 2);
        }

        parse_str($query, $args);
        $args[$key] = $value;

        return $base . '?' . http_build_query($args, '', '&', PHP_QUERY_RFC3986) . $fragment;
    }

    function add_submenu_page(string $parent, string $page, string $menu, string $capability, string $slug, callable $callback = null): string
    {
        WpStub::$submenus[] = ['parent' => $parent, 'page' => $page, 'menu' => $menu, 'capability' => $capability, 'slug' => $slug, 'callback' => $callback];

        return 'hook_' . $slug;
    }

    function wp_date(string $format, ?int $timestamp = null): string|false
    {
        return WpStub::$dateFails ? false : gmdate($format, $timestamp ?? time());
    }

    function wp_tempnam(string $filename = '', string $dir = ''): string
    {
        return WpStub::$tempFails ? '' : WpStub::temp();
    }

    function wp_delete_file(string $file): void
    {
        WpStub::$deleted[] = $file;

        if (is_file($file)) {
            unlink($file);
        }
    }

    /**
     * Behaves like WordPress for the parts that matter here: it records the
     * request, answers from the queue, and when asked to stream it writes the
     * body to the file whatever the status is, even for a redirect or an error.
     *
     * @param array<string, mixed> $args
     */
    function wp_safe_remote_get(string $url, array $args = []): array|WP_Error
    {
        WpStub::$requests[] = ['url' => $url, 'args' => $args];

        $answer = WpStub::next($url);

        if ($answer instanceof WP_Error || empty($args['stream'])) {
            return $answer;
        }

        $filename = (string) ($args['filename'] ?? '');

        if ($filename === '' || !is_writable(dirname($filename))) {
            return new WP_Error('http_request_failed', 'Destination directory for file streaming does not exist or is not writable.');
        }

        file_put_contents($filename, $answer['body']);
        $answer['body'] = '';
        $answer['filename'] = $filename;

        return $answer;
    }

    function wp_remote_retrieve_response_code(mixed $response): int|string
    {
        return is_array($response) && isset($response['response']['code']) ? $response['response']['code'] : '';
    }

    function wp_remote_retrieve_headers(mixed $response): iterable
    {
        return is_array($response) && isset($response['headers']) ? new ArrayObject($response['headers']) : [];
    }

    function wp_remote_retrieve_body(mixed $response): string
    {
        return is_array($response) && isset($response['body']) ? (string) $response['body'] : '';
    }
}
