<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Tests\Support;

use Closure;
use ScottOffen\WpGithubUpdater\Internal\Platform\HtmlSanitizer;


final class FakeHtmlSanitizer implements HtmlSanitizer
{
    /** @var list<string> */
    private array $inputs = [];

    private ?Closure $transform = null;


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
     * @return list<string>
     */
    public function inputs(): array
    {
        return $this->inputs;
    }
}
