<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Internal\WordPress;

use ScottOffen\WpGithubUpdater\Internal\Platform\Admin;

use function __;

/**
 * @internal
 */
final class WpAdmin implements Admin
{
    public function postUrl(string $action): string
    {
        return \admin_url('admin-post.php?action=' . rawurlencode($action));
    }

    public function can(string $capability): bool
    {
        return \current_user_can($capability);
    }

    public function respond(string $html): void
    {
        \nocache_headers();

        if (!headers_sent()) {
            header('Content-Type: text/html; charset=UTF-8');
        }

        echo $html;
    }

    public function deny(): void
    {
        \wp_die(__('Sorry, you are not allowed to access this page.'), '', ['response' => 403]);
    }

    public function terminate(): void
    {
        exit;
    }

    public function addSubmenuPage(string $parent, string $title, string $capability, string $slug, \Closure $render): void
    {
        \add_submenu_page($parent, $title, $title, $capability, $slug, $render);
    }

    public function nonceFields(string $action): string
    {
        return \wp_nonce_field($action, '_wpnonce', true, false);
    }

    public function verifyNonce(string $action): bool
    {
        $nonce = $_REQUEST['_wpnonce'] ?? null;

        return is_string($nonce) && \wp_verify_nonce($nonce, $action) !== false;
    }

    public function input(string $name): string
    {
        $value = $_POST[$name] ?? null;

        return is_string($value) ? (string) \wp_unslash($value) : '';
    }

    public function query(string $name): string
    {
        $value = $_GET[$name] ?? null;

        return is_string($value) ? (string) \wp_unslash($value) : '';
    }

    public function redirectBack(string $param, string $value): void
    {
        $referer = \wp_get_referer();
        $base = is_string($referer) && $referer !== '' ? $referer : \admin_url();

        \wp_safe_redirect(\add_query_arg($param, $value, $base));
    }

    public function dateTime(int $timestamp): string
    {
        $format = trim((string) \get_option('date_format', 'F j, Y') . ' ' . (string) \get_option('time_format', 'g:i a'));
        $formatted = \wp_date($format, $timestamp);

        return is_string($formatted) ? $formatted : gmdate('Y-m-d H:i', $timestamp);
    }
}
