<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Tests\Support;

use Closure;
use ScottOffen\WpGithubUpdater\Internal\Platform\HtmlSanitizer;

/**
 * A fake `HtmlSanitizer` for recording the HTML that it receives and for
 * transforming the HTML.
 */
final class FakeHtmlSanitizer implements HtmlSanitizer
{
    /** @var list<string> */
    private array $inputs = [];

    private ?Closure $transform = null;

    /**
     * Returns the HTML that was passed to the `post()` method.
     *
     * @return list<string> The values, in the order of the calls.
     */
    public function inputs(): array
    {
        return $this->inputs;
    }

    /**
     * Records the HTML and returns it unchanged, or transformed by the closure
     * from the `using()` method.
     */
    public function post(string $html): string
    {
        $this->inputs[] = $html;

        return $this->transform === null ? $html : ($this->transform)($html);
    }

    /**
     * Sets the closure that the `post()` method applies to the HTML.
     *
     * @return self The fake.
     */
    public function using(Closure $transform): self
    {
        $this->transform = $transform;

        return $this;
    }
}
