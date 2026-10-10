<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Tests\Release;

use PHPUnit\Framework\TestCase;
use ScottOffen\WpGithubUpdater\Internal\Release\Release;

final class ReleaseTest extends TestCase
{
    /** @return array<string, int|string> */
    private function stored(): array
    {
        return [
            'tag' => 'v1.2.3',
            'version' => '1.2.3',
            'notes' => "## What's Changed\n* A change",
            'published_at' => '2026-10-01T12:00:00Z',
            'html_url' => 'https://github.com/owner/repo/releases/tag/v1.2.3',
            'asset_id' => 42,
            'asset_name' => 'my-plugin-1.2.3.zip',
            'asset_url' => 'https://api.github.com/repos/owner/repo/releases/assets/42',
            'asset_size' => 2048,
            'asset_digest' => 'sha256:abc123',
        ];
    }

    public function testAReleaseSurvivesAnArrayRoundTrip(): void
    {
        $release = Release::fromArray($this->stored());

        $this->assertNotNull($release);
        $this->assertSame($this->stored(), $release->toArray());
        $this->assertSame('v1.2.3', $release->tag);
        $this->assertSame('1.2.3', $release->version);
        $this->assertSame(42, $release->assetId);
        $this->assertSame('sha256:abc123', $release->assetDigest);
    }

    public function testNotesDigestAndDatesMayBeEmpty(): void
    {
        $data = $this->stored();
        $data['notes'] = '';
        $data['published_at'] = '';
        $data['html_url'] = '';
        $data['asset_digest'] = '';

        $release = Release::fromArray($data);

        $this->assertNotNull($release);
        $this->assertSame('', $release->notes);
        $this->assertSame('', $release->assetDigest);
    }

    public function testExtraKeysAreIgnored(): void
    {
        $data = $this->stored();
        $data['added_by_a_newer_version'] = true;

        $release = Release::fromArray($data);

        $this->assertNotNull($release);
        $this->assertSame($this->stored(), $release->toArray());
    }

    public function testAnythingThatIsNotAnArrayIsRejected(): void
    {
        foreach ([null, false, '', 'v1.2.3', 42, new \stdClass()] as $value) {
            $this->assertNull(Release::fromArray($value), var_export($value, true));
        }
    }

    public function testAMissingKeyIsRejected(): void
    {
        foreach (array_keys($this->stored()) as $key) {
            $data = $this->stored();
            unset($data[$key]);

            $this->assertNull(Release::fromArray($data), $key);
        }
    }

    public function testAKeyOfTheWrongTypeIsRejected(): void
    {
        $wrong = [
            'tag' => 123,
            'version' => null,
            'notes' => ['a'],
            'published_at' => 1,
            'html_url' => false,
            'asset_id' => '42',
            'asset_name' => 7,
            'asset_url' => null,
            'asset_size' => '2048',
            'asset_digest' => 0,
        ];

        foreach ($wrong as $key => $value) {
            $data = $this->stored();
            $data[$key] = $value;

            $this->assertNull(Release::fromArray($data), $key);
        }
    }

    public function testTheKeysThatIdentifyTheReleaseAndAssetMustNotBeEmpty(): void
    {
        $empty = [
            'tag' => '',
            'version' => '',
            'asset_name' => '',
            'asset_url' => '',
            'asset_id' => 0,
            'asset_size' => -1,
        ];

        foreach ($empty as $key => $value) {
            $data = $this->stored();
            $data[$key] = $value;

            $this->assertNull(Release::fromArray($data), $key);
        }
    }
}
