<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Internal\Platform;

/**
 * The operations for addresses, capability checks, responses, menu pages,
 * nonces, request values, redirects, and date formats in the admin area.
 *
 * @internal
 */
interface Admin
{
    /**
     * Adds a page to a submenu of the admin menu.
     *
     * @param string $parent The slug of the parent menu.
     * @param string $title The title of the page and of its menu entry.
     * @param string $capability The capability that is required to see the
     *     page.
     * @param string $slug The slug of the page.
     * @param \Closure $render The closure that prints the page.
     */
    public function addSubmenuPage(string $parent, string $title, string $capability, string $slug, \Closure $render): void;

    /**
     * Indicates whether the current user has a capability.
     *
     * @param string $capability The name of the capability.
     *
     * @return bool True when the current user has the capability.
     */
    public function can(string $capability): bool;

    /**
     * Returns a timestamp formatted as a date and time.
     *
     * @param int $timestamp A Unix timestamp.
     *
     * @return string The formatted date and time.
     */
    public function dateTime(int $timestamp): string;

    /**
     * Responds with 403 Forbidden.
     */
    public function deny(): void;

    /**
     * Returns the value of a field of the submitted form.
     *
     * @param string $name The name of the field.
     *
     * @return string The value as it was typed, or an empty string when the
     *     field is missing or isn't a string.
     */
    public function input(string $name): string;

    /**
     * Returns the hidden fields for a nonce.
     *
     * @param string $action The action that the nonce is for.
     *
     * @return string The HTML for the hidden fields.
     */
    public function nonceFields(string $action): string;

    /**
     * Returns the address for posting a form to an action.
     *
     * @param string $action The name of the action.
     *
     * @return string The address for the action.
     */
    public function postUrl(string $action): string;

    /**
     * Returns the value of a query parameter.
     *
     * @param string $name The name of the parameter.
     *
     * @return string The value, or an empty string when the parameter is
     *     missing or isn't a string.
     */
    public function query(string $name): string;

    /**
     * Redirects to the referring page, or to the admin address when there is
     * no referring page, with a query parameter added to the address.
     *
     * The method doesn't end the request.
     *
     * @param string $param The name of the parameter.
     * @param string $value The value of the parameter.
     */
    public function redirectBack(string $param, string $value): void;

    /**
     * Outputs an HTML document as the response.
     *
     * @param string $html The whole HTML document.
     */
    public function respond(string $html): void;

    /**
     * Ends the request.
     */
    public function terminate(): void;

    /**
     * Indicates whether the request has a valid nonce for an action.
     *
     * @param string $action The action that the nonce is for.
     *
     * @return bool True when the request has a nonce that is valid for the
     *     action.
     */
    public function verifyNonce(string $action): bool;
}
