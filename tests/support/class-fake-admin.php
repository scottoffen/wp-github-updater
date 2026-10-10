<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Tests\Support;

use Closure;
use ScottOffen\WpGithubUpdater\Internal\Platform\Admin;

/**
 * A fake `Admin` for recording the calls that a test makes and for returning
 * the values that the test sets.
 */
final class FakeAdmin implements Admin
{
    /** @var array<string, true> */
    private array $allowed = [];

    /** @var list<string> */
    private array $responses = [];

    private int $denied = 0;

    private int $terminated = 0;

    /**
     * @var list<array{parent: string, title: string, capability: string,
     *     slug: string, render: Closure}>
     */
    private array $pages = [];

    /** @var array<string, string> */
    private array $inputs = [];

    /** @var array<string, string> */
    private array $queries = [];

    /** @var list<string> */
    private array $verified = [];

    private bool $nonceValid = true;

    /** @var list<array{string, string}> */
    private array $redirects = [];

    public function addSubmenuPage(string $parent, string $title, string $capability, string $slug, Closure $render): void
    {
        $this->pages[] = ['parent' => $parent, 'title' => $title, 'capability' => $capability, 'slug' => $slug, 'render' => $render];
    }

    /**
     * Sets the capabilities for which `can()` returns true.
     *
     * @param string ...$capabilities The capabilities.
     */
    public function allow(string ...$capabilities): void
    {
        foreach ($capabilities as $capability) {
            $this->allowed[$capability] = true;
        }
    }

    public function can(string $capability): bool
    {
        return isset($this->allowed[$capability]);
    }

    /**
     * Returns `T` followed by the timestamp.
     *
     * @return string The text.
     */
    public function dateTime(int $timestamp): string
    {
        return 'T' . $timestamp;
    }

    /**
     * Returns the number of calls to `deny()`.
     *
     * @return int The number of calls.
     */
    public function denied(): int
    {
        return $this->denied;
    }

    public function deny(): void
    {
        $this->denied++;
    }

    public function input(string $name): string
    {
        return $this->inputs[$name] ?? '';
    }

    /**
     * Makes `verifyNonce()` return false from then on.
     */
    public function invalidateNonces(): void
    {
        $this->nonceValid = false;
    }

    /**
     * Returns a hidden `_wpnonce` input with the value `nonce:` followed by the
     * action.
     *
     * @return string The HTML for the input.
     */
    public function nonceFields(string $action): string
    {
        return '<input type="hidden" name="_wpnonce" value="nonce:' . $action . '">';
    }

    /**
     * Returns the recorded menu pages.
     *
     * @return list<array{parent: string, title: string, capability: string,
     *     slug: string, render: Closure}> The pages, in the order of the calls.
     */
    public function pages(): array
    {
        return $this->pages;
    }

    /**
     * Returns `https://example.test/wp-admin/admin-post.php?action=` followed
     * by the encoded action.
     *
     * @return string The address.
     */
    public function postUrl(string $action): string
    {
        return 'https://example.test/wp-admin/admin-post.php?action=' . rawurlencode($action);
    }

    public function query(string $name): string
    {
        return $this->queries[$name] ?? '';
    }

    public function redirectBack(string $param, string $value): void
    {
        $this->redirects[] = [$param, $value];
    }

    /**
     * Returns the recorded redirects.
     *
     * @return list<array{string, string}> The parameter and value of each
     *     redirect, in the order of the calls.
     */
    public function redirects(): array
    {
        return $this->redirects;
    }

    public function respond(string $html): void
    {
        $this->responses[] = $html;
    }

    /**
     * Returns the recorded responses.
     *
     * @return list<string> The HTML documents, in the order of the calls.
     */
    public function responses(): array
    {
        return $this->responses;
    }

    public function terminate(): void
    {
        $this->terminated++;
    }

    /**
     * Returns the number of calls to `terminate()`.
     *
     * @return int The number of calls.
     */
    public function terminated(): int
    {
        return $this->terminated;
    }

    /**
     * Returns the actions that were passed to `verifyNonce()`.
     *
     * @return list<string> The actions, in the order of the calls.
     */
    public function verified(): array
    {
        return $this->verified;
    }

    /**
     * Records the action and returns true until `invalidateNonces()` is
     * called.
     *
     * @return bool True, or false after `invalidateNonces()`.
     */
    public function verifyNonce(string $action): bool
    {
        $this->verified[] = $action;

        return $this->nonceValid;
    }

    /**
     * Sets the value that `input()` returns for a field.
     */
    public function withInput(string $name, string $value): void
    {
        $this->inputs[$name] = $value;
    }

    /**
     * Sets the value that `query()` returns for a parameter.
     */
    public function withQuery(string $name, string $value): void
    {
        $this->queries[$name] = $value;
    }
}
