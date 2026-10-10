<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Tests\WordPress;

use PHPUnit\Framework\TestCase;
use ScottOffen\WpGithubUpdater\Internal\WordPress\WpHttpClient;
use ScottOffen\WpGithubUpdater\Tests\Support\WpStub;
use WP_Error;

final class WpHttpClientTest extends TestCase
{
    private string $destination;

    protected function setUp(): void
    {
        WpStub::reset();
        $this->destination = WpStub::temp();
    }

    protected function tearDown(): void
    {
        if (is_dir($this->destination)) {
            rmdir($this->destination);
        }

        foreach ([$this->destination . '.part'] as $scratch) {
            if (is_file($scratch)) {
                unlink($scratch);
            }
        }

        WpStub::reset();
    }

    public function testAGetSendsTheHeadersWithNoRedirectsAndAShortTimeout(): void
    {
        WpStub::queue(WpStub::response(200, [], '[]'));
        $headers = ['Accept' => 'application/vnd.github+json', 'Authorization' => 'Bearer secret-token'];

        (new WpHttpClient())->get('https://api.github.com/repos/o/r/releases', $headers);

        $this->assertCount(1, WpStub::$requests);
        $this->assertSame('https://api.github.com/repos/o/r/releases', WpStub::$requests[0]['url']);
        $this->assertSame([
            'headers' => $headers,
            'redirection' => 0,
            'timeout' => 15,
        ], WpStub::$requests[0]['args']);
    }

    public function testAGetReturnsTheStatusHeadersAndBody(): void
    {
        WpStub::queue(WpStub::response(200, ['ETag' => 'W/"abc"', 'X-RateLimit-Remaining' => '59'], '[{"tag_name":"v1.0.0"}]'));

        $response = (new WpHttpClient())->get('https://api.github.com/x');

        $this->assertSame(200, $response->status);
        $this->assertSame('[{"tag_name":"v1.0.0"}]', $response->body);
        $this->assertSame('W/"abc"', $response->header('etag'));
        $this->assertSame('59', $response->header('X-RateLimit-Remaining'));
        $this->assertFalse($response->failed());
    }

    public function testAHeaderSentMoreThanOnceIsJoined(): void
    {
        WpStub::queue(WpStub::response(200, ['Set-Cookie' => ['a=1', 'b=2']]));

        $response = (new WpHttpClient())->get('https://api.github.com/x');

        $this->assertSame('a=1, b=2', $response->header('set-cookie'));
    }

    public function testARedirectComesBackAsARedirect(): void
    {
        WpStub::queue(WpStub::response(302, ['Location' => 'https://objects.example.com/x'], ''));

        $response = (new WpHttpClient())->get('https://api.github.com/x');

        $this->assertTrue($response->isRedirect());
        $this->assertSame('https://objects.example.com/x', $response->header('location'));
        $this->assertCount(1, WpStub::$requests);
    }

    public function testAnErrorFromWordPressIsAFailureWithItsMessage(): void
    {
        WpStub::queue(new WP_Error('http_request_failed', 'cURL error 28: Operation timed out'));

        $response = (new WpHttpClient())->get('https://api.github.com/x');

        $this->assertTrue($response->failed());
        $this->assertSame(0, $response->status);
        $this->assertSame('cURL error 28: Operation timed out', $response->error);
    }

    public function testADownloadStreamsToAScratchFileWithNoRedirectsAndALongTimeout(): void
    {
        WpStub::queue(WpStub::response(200, [], 'ZIP'));
        $headers = ['User-Agent' => 'scottoffen-wp-github-updater'];

        (new WpHttpClient())->download('https://storage.example.com/a.zip', $headers, $this->destination);

        $this->assertSame([
            'headers' => $headers,
            'redirection' => 0,
            'timeout' => 300,
            'stream' => true,
            'filename' => $this->destination . '.part',
        ], WpStub::$requests[0]['args']);
    }

    public function testASuccessfulDownloadLandsInTheDestinationWithAnEmptyBody(): void
    {
        file_put_contents($this->destination, 'OLD CONTENT');
        WpStub::queue(WpStub::response(200, ['Content-Type' => 'application/zip'], 'THE ZIP BYTES'));

        $response = (new WpHttpClient())->download('https://storage.example.com/a.zip', [], $this->destination);

        $this->assertSame(200, $response->status);
        $this->assertSame('', $response->body);
        $this->assertSame('application/zip', $response->header('content-type'));
        $this->assertSame('THE ZIP BYTES', file_get_contents($this->destination));
        $this->assertFileDoesNotExist($this->destination . '.part');
    }

    public function testAnErrorResponseLeavesTheDestinationUnchanged(): void
    {
        file_put_contents($this->destination, 'ORIGINAL');
        WpStub::queue(WpStub::response(404, ['X-Request-Id' => 'abc'], '{"message":"Not Found"}'));

        $response = (new WpHttpClient())->download('https://api.github.com/x', [], $this->destination);

        $this->assertSame(404, $response->status);
        $this->assertSame('', $response->body);
        $this->assertSame('abc', $response->header('x-request-id'));
        $this->assertSame('ORIGINAL', file_get_contents($this->destination));
        $this->assertFileDoesNotExist($this->destination . '.part');
    }

    public function testARedirectResponseLeavesTheDestinationUnchangedAndReturnsTheLocation(): void
    {
        file_put_contents($this->destination, 'ORIGINAL');
        WpStub::queue(WpStub::response(302, ['Location' => 'https://storage.example.com/a.zip'], 'Found. Redirecting.'));

        $response = (new WpHttpClient())->download('https://api.github.com/x', [], $this->destination);

        $this->assertTrue($response->isRedirect());
        $this->assertSame('https://storage.example.com/a.zip', $response->header('location'));
        $this->assertSame('', $response->body);
        $this->assertSame('ORIGINAL', file_get_contents($this->destination));
        $this->assertFileDoesNotExist($this->destination . '.part');
    }

    public function testEveryNonSuccessStatusLeavesTheDestinationAsItWas(): void
    {
        foreach ([199, 300, 301, 304, 400, 401, 403, 429, 500, 503] as $status) {
            WpStub::reset();
            file_put_contents($this->destination, 'ORIGINAL');
            WpStub::queue(WpStub::response($status, [], 'BODY FOR ' . $status));

            $response = (new WpHttpClient())->download('https://api.github.com/x', [], $this->destination);

            $this->assertSame($status, $response->status, (string) $status);
            $this->assertSame('ORIGINAL', file_get_contents($this->destination), (string) $status);
            $this->assertFileDoesNotExist($this->destination . '.part', (string) $status);
        }
    }

    public function testEveryTwoHundredStatusIsASuccess(): void
    {
        foreach ([200, 201, 204, 299] as $status) {
            WpStub::reset();
            file_put_contents($this->destination, 'ORIGINAL');
            WpStub::queue(WpStub::response($status, [], 'BODY'));

            (new WpHttpClient())->download('https://api.github.com/x', [], $this->destination);

            $this->assertSame('BODY', file_get_contents($this->destination), (string) $status);
        }
    }

    public function testAnErrorFromWordPressDuringADownloadIsAFailureAndLeavesTheDestination(): void
    {
        file_put_contents($this->destination, 'ORIGINAL');
        WpStub::queue(new WP_Error('http_request_failed', 'timed out'));

        $response = (new WpHttpClient())->download('https://api.github.com/x', [], $this->destination);

        $this->assertTrue($response->failed());
        $this->assertSame('timed out', $response->error);
        $this->assertSame('ORIGINAL', file_get_contents($this->destination));
        $this->assertFileDoesNotExist($this->destination . '.part');
    }

    public function testADownloadThatCannotBeMovedIntoPlaceIsAFailureWithNoScratchFileLeft(): void
    {
        unlink($this->destination);
        mkdir($this->destination);
        WpStub::queue(WpStub::response(200, [], 'ZIP'));

        $response = (new WpHttpClient())->download('https://api.github.com/x', [], $this->destination);

        $this->assertTrue($response->failed());
        $this->assertSame('The download could not be written to its destination.', $response->error);
        $this->assertFileDoesNotExist($this->destination . '.part');
        $this->assertContains($this->destination . '.part', WpStub::$deleted);
    }

    public function testADestinationInAMissingDirectoryIsAFailure(): void
    {
        $destination = sys_get_temp_dir() . '/wpgu-missing-' . bin2hex(random_bytes(6)) . '/file.zip';
        WpStub::queue(WpStub::response(200, [], 'ZIP'));

        $response = (new WpHttpClient())->download('https://api.github.com/x', [], $destination);

        $this->assertTrue($response->failed());
        $this->assertFileDoesNotExist($destination);
    }
}
