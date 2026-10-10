<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Internal\WordPress;

use ScottOffen\WpGithubUpdater\Internal\Platform\Admin;

use function __;

/**
 * The implementation of the `Admin` interface for WordPress.
 *
 * @internal
 */
final class WpAdmin implements Admin
{
    public function addSubmenuPage(string $parent, string $title, string $capability, string $slug, \Closure $render): void
    {
        \add_submenu_page($parent, $title, $title, $capability, $slug, $render);
    }

    public function can(string $capability): bool
    {
        return \current_user_can($capability);
    }

    /**
     * Returns a timestamp formatted by the `wp_date()` function with the
     * `date_format` and `time_format` options.
     *
     * @return string The formatted date and time, or the timestamp formatted as
     *     `Y-m-d H:i` in UTC when the `wp_date()` function doesn't return a
     *     string.
     */
    public function dateTime(int $timestamp): string
    {
        $format = trim((string) \get_option('date_format', 'F j, Y') . ' ' . (string) \get_option('time_format', 'g:i a'));
        $formatted = \wp_date($format, $timestamp);

        return is_string($formatted) ? $formatted : gmdate('Y-m-d H:i', $timestamp);
    }

    /**
     * Calls the `wp_die()` function with the message "Sorry, you are not
     * allowed to access this page." and the response code 403.
     */
    public function deny(): void
    {
        \wp_die(__('Sorry, you are not allowed to access this page.'), '', ['response' => 403]);
    }

    /**
     * Returns the value of an entry in `$_POST`, with the slashes removed by
     * the `wp_unslash()` function.
     *
     * @return string The value, or an empty string when the entry is missing or
     *     isn't a string.
     */
    public function input(string $name): string
    {
        $value = $_POST[$name] ?? null;

        return is_string($value) ? (string) \wp_unslash($value) : '';
    }

    /**
     * Returns the hidden fields from the `wp_nonce_field()` function, with the
     * name `_wpnonce` and the referer field.
     *
     * @return string The HTML for the hidden fields.
     */
    public function nonceFields(string $action): string
    {
        return \wp_nonce_field($action, '_wpnonce', true, false);
    }

    /**
     * Returns the `admin_url()` address of `admin-post.php` with the action as
     * the `action` query parameter.
     *
     * @return string The address, with the action encoded by `rawurlencode()`.
     */
    public function postUrl(string $action): string
    {
        return \admin_url('admin-post.php?action=' . rawurlencode($action));
    }

    /**
     * Returns the value of an entry in `$_GET`, with the slashes removed by the
     * `wp_unslash()` function.
     *
     * @return string The value, or an empty string when the entry is missing or
     *     isn't a string.
     */
    public function query(string $name): string
    {
        $value = $_GET[$name] ?? null;

        return is_string($value) ? (string) \wp_unslash($value) : '';
    }

    /**
     * Redirects with the `wp_safe_redirect()` function to the address from the
     * `wp_get_referer()` function, or to the `admin_url()` address when that
     * address is missing or empty.
     *
     * The `add_query_arg()` function adds the query parameter to the address.
     */
    public function redirectBack(string $param, string $value): void
    {
        $referer = \wp_get_referer();
        $base = is_string($referer) && $referer !== '' ? $referer : \admin_url();

        \wp_safe_redirect(\add_query_arg($param, $value, $base));
    }

    /**
     * Outputs an HTML document after sending the headers from the
     * `nocache_headers()` function.
     *
     * When headers haven't been sent, the method also sends a `Content-Type`
     * header with the value `text/html; charset=UTF-8`.
     */
    public function respond(string $html): void
    {
        \nocache_headers();

        if (!headers_sent()) {
            header('Content-Type: text/html; charset=UTF-8');
        }

        echo $html;
    }

    public function terminate(): void
    {
        exit;
    }

    /**
     * Verifies the `_wpnonce` entry of `$_REQUEST` with the `wp_verify_nonce()`
     * function.
     *
     * @return bool True when the entry is a string and the `wp_verify_nonce()`
     *     function doesn't return `false`.
     */
    public function verifyNonce(string $action): bool
    {
        $nonce = $_REQUEST['_wpnonce'] ?? null;

        return is_string($nonce) && \wp_verify_nonce($nonce, $action) !== false;
    }
}
