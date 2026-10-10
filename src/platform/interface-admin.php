<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Platform;

/**
 * The parts of the platform's admin area the library uses.
 */
interface Admin
{
    /**
     * The address of the handler the platform runs for an admin action.
     *
     * @param string $action The name of the action.
     */
    public function postUrl(string $action): string;

    /**
     * Whether the signed-in person has a capability.
     *
     * @param string $capability The name of the capability.
     */
    public function can(string $capability): bool;

    /**
     * Sends an HTML document as the answer to the request, with headers that
     * keep it from being cached.
     *
     * @param string $html The whole document.
     */
    public function respond(string $html): void;

    /**
     * Refuses the request because the person isn't allowed to see it.
     */
    public function deny(): void;

    /**
     * Ends the request. Nothing runs after this.
     */
    public function terminate(): void;

    /**
     * Adds a page to the admin menu, under another menu.
     *
     * @param string $parent The slug of the menu to add it under.
     * @param string $title The title of the page and of its menu entry.
     * @param string $capability The capability needed to see the page.
     * @param string $slug The slug of the page.
     * @param \Closure $render Prints the page.
     */
    public function addSubmenuPage(string $parent, string $title, string $capability, string $slug, \Closure $render): void;

    /**
     * The hidden fields that make a form's request provable. They carry a
     * nonce for the action and the address of the page the form is on.
     *
     * @param string $action The action the nonce is for.
     *
     * @return string HTML for the hidden fields.
     */
    public function nonceFields(string $action): string;

    /**
     * Whether the request carries a valid nonce for the action.
     *
     * @param string $action The action the nonce is for.
     */
    public function verifyNonce(string $action): bool;

    /**
     * A value from the submitted form.
     *
     * @param string $name The name of the field.
     *
     * @return string The value as the person typed it, or an empty string
     *     when the field is missing or isn't text.
     */
    public function input(string $name): string;

    /**
     * A value from the address of the request.
     *
     * @param string $name The name of the parameter.
     *
     * @return string The value, or an empty string when the parameter is
     *     missing or isn't text.
     */
    public function query(string $name): string;

    /**
     * Redirects to the page the form came from, with one parameter added or
     * replaced. It only redirects to an address the site allows, and falls
     * back to the admin home. It doesn't end the request.
     *
     * @param string $param The name of the parameter.
     * @param string $value Its value.
     */
    public function redirectBack(string $param, string $value): void;

    /**
     * Formats a time the way the site is set to show times.
     *
     * @param int $timestamp A Unix timestamp.
     */
    public function dateTime(int $timestamp): string;
}
