<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Tests\Support;

use Closure;
use ScottOffen\WpGithubUpdater\Internal\Platform\Admin;


final class FakeAdmin implements Admin
{
    /** @var array<string, true> */
    private array $allowed = [];

    /** @var list<string> */
    private array $responses = [];

    private int $denied = 0;

    private int $terminated = 0;

    /** @var list<array{parent: string, title: string, capability: string, slug: string, render: Closure}> */
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


    public function allow(string ...$capabilities): void
    {
        foreach ($capabilities as $capability) {
            $this->allowed[$capability] = true;
        }
    }


    public function withInput(string $name, string $value): void
    {
        $this->inputs[$name] = $value;
    }


    public function withQuery(string $name, string $value): void
    {
        $this->queries[$name] = $value;
    }


    public function invalidateNonces(): void
    {
        $this->nonceValid = false;
    }

    public function postUrl(string $action): string
    {
        return 'https://example.test/wp-admin/admin-post.php?action=' . rawurlencode($action);
    }

    public function can(string $capability): bool
    {
        return isset($this->allowed[$capability]);
    }

    public function respond(string $html): void
    {
        $this->responses[] = $html;
    }

    public function deny(): void
    {
        $this->denied++;
    }

    public function terminate(): void
    {
        $this->terminated++;
    }

    public function addSubmenuPage(string $parent, string $title, string $capability, string $slug, Closure $render): void
    {
        $this->pages[] = ['parent' => $parent, 'title' => $title, 'capability' => $capability, 'slug' => $slug, 'render' => $render];
    }

    public function nonceFields(string $action): string
    {
        return '<input type="hidden" name="_wpnonce" value="nonce:' . $action . '">';
    }

    public function verifyNonce(string $action): bool
    {
        $this->verified[] = $action;

        return $this->nonceValid;
    }

    public function input(string $name): string
    {
        return $this->inputs[$name] ?? '';
    }

    public function query(string $name): string
    {
        return $this->queries[$name] ?? '';
    }

    public function redirectBack(string $param, string $value): void
    {
        $this->redirects[] = [$param, $value];
    }

    public function dateTime(int $timestamp): string
    {
        return 'T' . $timestamp;
    }

    /**
     * @return list<string>
     */
    public function responses(): array
    {
        return $this->responses;
    }


    public function denied(): int
    {
        return $this->denied;
    }


    public function terminated(): int
    {
        return $this->terminated;
    }

    /**
     * @return list<array{parent: string, title: string, capability: string, slug: string, render: Closure}>
     */
    public function pages(): array
    {
        return $this->pages;
    }

    /**
     * @return list<string>
     */
    public function verified(): array
    {
        return $this->verified;
    }

    /**
     * @return list<array{string, string}>
     */
    public function redirects(): array
    {
        return $this->redirects;
    }
}
