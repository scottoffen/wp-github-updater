<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Tests\Http;

use PHPUnit\Framework\TestCase;
use ScottOffen\WpGithubUpdater\Http\HttpResponse;

final class HttpResponseTest extends TestCase
{
    public function testHeaderNamesAreLowercasedAndLookedUpInAnyCase(): void
    {
        $response = new HttpResponse(200, ['ETag' => 'W/"abc"', 'X-RateLimit-Remaining' => '59']);

        $this->assertSame(['etag' => 'W/"abc"', 'x-ratelimit-remaining' => '59'], $response->headers);
        $this->assertSame('W/"abc"', $response->header('ETAG'));
        $this->assertSame('59', $response->header('x-ratelimit-remaining'));
    }

    public function testAMissingHeaderIsNull(): void
    {
        $this->assertNull((new HttpResponse(200))->header('location'));
    }

    public function testAHeaderSentMoreThanOnceIsJoined(): void
    {
        $response = new HttpResponse(200, ['Set-Cookie' => ['a=1', 'b=2']]);

        $this->assertSame('a=1, b=2', $response->header('set-cookie'));
    }

    public function testTheStatusBodyAndErrorAreKept(): void
    {
        $response = new HttpResponse(404, [], '{"message":"Not Found"}', 'oops');

        $this->assertSame(404, $response->status);
        $this->assertSame('{"message":"Not Found"}', $response->body);
        $this->assertSame('oops', $response->error);
    }

    public function testAFailureHasStatusZeroAndTheError(): void
    {
        $response = HttpResponse::failure('Could not resolve host');

        $this->assertSame(0, $response->status);
        $this->assertSame('Could not resolve host', $response->error);
        $this->assertSame('', $response->body);
        $this->assertTrue($response->failed());
    }

    public function testOnlyStatusZeroIsAFailure(): void
    {
        foreach ([200, 304, 404, 500] as $status) {
            $this->assertFalse((new HttpResponse($status))->failed(), (string) $status);
        }
    }

    public function testTheRedirectStatusesAreRedirects(): void
    {
        foreach ([301, 302, 303, 307, 308] as $status) {
            $this->assertTrue((new HttpResponse($status))->isRedirect(), (string) $status);
        }

        foreach ([0, 200, 204, 300, 304, 305, 404] as $status) {
            $this->assertFalse((new HttpResponse($status))->isRedirect(), (string) $status);
        }
    }
}
