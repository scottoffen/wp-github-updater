<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Tests\Support;

use Closure;
use ScottOffen\WpGithubUpdater\Platform\HtmlSanitizer;

/**
 * A sanitizer that returns its input as it is, unless the test gives it
 * something to do, and remembers what it was asked to clean.
 */
final class FakeHtmlSanitizer implements HtmlSanitizer
{
    /** @var list<string> */
    private array $inputs = [];

    private ?Closure $transform = null;

    /** Sets what the sanitizer does to the HTML it is given. */
    public function using(Closure $transform): self
    {
        $this->transform = $transform;

        return $this;
    }

    public function post(string $html): string
    {
        $this->inputs[] = $html;

        return $this->transform === null ? $html : ($this->transform)($html);
    }

    /**
     * Every piece of HTML the sanitizer was asked to clean, oldest first.
     *
     * @return list<string>
     */
    public function inputs(): array
    {
        return $this->inputs;
    }
}
