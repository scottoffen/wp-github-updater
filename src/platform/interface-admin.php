<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Internal\Platform;

/**
 * @internal
 */
interface Admin
{
    /**
     * @param string $action The name of the action.
     */
    public function postUrl(string $action): string;

    /**
     * @param string $capability The name of the capability.
     */
    public function can(string $capability): bool;

    /**
     * @param string $html The whole document.
     */
    public function respond(string $html): void;


    public function deny(): void;


    public function terminate(): void;

    /**
     * @param string $parent The slug of the menu to add it under.
     * @param string $title The title of the page and of its menu entry.
     * @param string $capability The capability that is required to see the page.
     * @param string $slug The slug of the page.
     * @param \Closure $render Prints the page.
     */
    public function addSubmenuPage(string $parent, string $title, string $capability, string $slug, \Closure $render): void;

    /**
     * @param string $action The action the nonce is for.
     *
     * @return string HTML for the hidden fields.
     */
    public function nonceFields(string $action): string;

    /**
     * @param string $action The action the nonce is for.
     */
    public function verifyNonce(string $action): bool;

    /**
     * @param string $name The name of the field.
     *
     * @return string The value as the person typed it, or an empty string
     *     when the field is missing or isn't text.
     */
    public function input(string $name): string;

    /**
     * @param string $name The name of the parameter.
     *
     * @return string The value, or an empty string when the parameter is
     *     missing or isn't text.
     */
    public function query(string $name): string;

    /**
     * @param string $param The name of the parameter.
     * @param string $value Its value.
     */
    public function redirectBack(string $param, string $value): void;

    /**
     * @param int $timestamp A Unix timestamp.
     */
    public function dateTime(int $timestamp): string;
}
