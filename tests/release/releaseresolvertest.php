<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Tests\Release;

use PHPUnit\Framework\TestCase;
use ScottOffen\WpGithubUpdater\Internal\Release\Outcome;
use ScottOffen\WpGithubUpdater\Internal\Release\Release;
use ScottOffen\WpGithubUpdater\Internal\Release\ReleaseResolver;

final class ReleaseResolverTest extends TestCase
{
    private const PATTERN = 'my-plugin-*.zip';

    private function resolver(): ReleaseResolver
    {
        return new ReleaseResolver(self::PATTERN);
    }

    /**
     * @param array<string, mixed> $changes Keys to replace.
     *
     * @return array<string, mixed>
     */
    private function asset(string $name, array $changes = []): array
    {
        return array_merge([
            'id' => 42,
            'name' => $name,
            'url' => 'https://api.github.com/repos/owner/repo/releases/assets/42',
            'size' => 2048,
            'digest' => 'sha256:abc123',
        ], $changes);
    }

    /**
     * @param array<string, mixed> $changes Keys to replace.
     *
     * @return array<string, mixed>
     */
    private function release(string $tag, array $changes = []): array
    {
        return array_merge([
            'tag_name' => $tag,
            'draft' => false,
            'prerelease' => false,
            'body' => 'Some notes',
            'published_at' => '2026-10-01T12:00:00Z',
            'html_url' => 'https://github.com/owner/repo/releases/tag/' . $tag,
            'assets' => [$this->asset('my-plugin-' . ltrim($tag, 'v') . '.zip')],
        ], $changes);
    }

    public function testAReleaseThatMeetsTheConditionsIsResolvedWithAllItsDetails(): void
    {
        $result = $this->resolver()->resolve([$this->release('v1.2.3')]);

        $this->assertInstanceOf(Release::class, $result);
        $this->assertSame('v1.2.3', $result->tag);
        $this->assertSame('1.2.3', $result->version);
        $this->assertSame('Some notes', $result->notes);
        $this->assertSame('2026-10-01T12:00:00Z', $result->publishedAt);
        $this->assertSame('https://github.com/owner/repo/releases/tag/v1.2.3', $result->htmlUrl);
        $this->assertSame(42, $result->assetId);
        $this->assertSame('my-plugin-1.2.3.zip', $result->assetName);
        $this->assertSame('https://api.github.com/repos/owner/repo/releases/assets/42', $result->assetUrl);
        $this->assertSame(2048, $result->assetSize);
        $this->assertSame('sha256:abc123', $result->assetDigest);
    }

    public function testMissingNotesAndDigestBecomeEmptyStrings(): void
    {
        $release = $this->release('v1.0.0', [
            'body' => null,
            'assets' => [$this->asset('my-plugin-1.0.0.zip', ['digest' => null])],
        ]);

        $result = $this->resolver()->resolve([$release]);

        $this->assertInstanceOf(Release::class, $result);
        $this->assertSame('', $result->notes);
        $this->assertSame('', $result->assetDigest);
    }

    public function testAnEmptyListHasNoRelease(): void
    {
        $this->assertSame(Outcome::NO_RELEASE, $this->resolver()->resolve([]));
    }

    public function testDraftsAndPrereleasesAreSkipped(): void
    {
        $releases = [
            $this->release('v2.0.0', ['draft' => true]),
            $this->release('v1.9.0', ['prerelease' => true]),
            $this->release('v1.0.0'),
        ];

        $result = $this->resolver()->resolve($releases);

        $this->assertInstanceOf(Release::class, $result);
        $this->assertSame('1.0.0', $result->version);
    }

    public function testAReleaseThatDoesNotSayItIsStableIsSkipped(): void
    {
        $noDraft = $this->release('v2.0.0');
        unset($noDraft['draft']);

        $noPrerelease = $this->release('v1.9.0');
        unset($noPrerelease['prerelease']);

        $oddDraft = $this->release('v1.8.0', ['draft' => 0]);

        $this->assertSame(
            Outcome::NO_RELEASE,
            $this->resolver()->resolve([$noDraft, $noPrerelease, $oddDraft]),
        );
    }

    public function testOnlyTagsOfVAndThreeNumbersAreAccepted(): void
    {
        $tags = [
            '1.2.3',
            'V1.2.3',
            'v1.2',
            'v1.2.3.4',
            'v01.2.3',
            'v1.02.3',
            'v1.2.3-beta.1',
            'v1.2.3+build',
            'v1.2.x',
            'release-1.2.3',
            '',
        ];

        foreach ($tags as $tag) {
            $result = $this->resolver()->resolve([$this->release($tag)]);

            $this->assertSame(Outcome::NO_RELEASE, $result, $tag);
        }
    }

    public function testASuffixedTagIsSkippedEvenWhenGitHubCallsItStable(): void
    {
        $result = $this->resolver()->resolve([$this->release('v2.0.0-beta.1', ['prerelease' => false])]);

        $this->assertSame(Outcome::NO_RELEASE, $result);
    }

    public function testItemsThatAreNotReleasesAreSkipped(): void
    {
        $result = $this->resolver()->resolve([null, 'v9.9.9', 42, [], ['tag_name' => 7], $this->release('v1.0.0')]);

        $this->assertInstanceOf(Release::class, $result);
        $this->assertSame('1.0.0', $result->version);
    }

    public function testTheHighestVersionIsSelectedWhateverTheListOrder(): void
    {
        $releases = [
            $this->release('v1.2.0'),
            $this->release('v1.10.0'),
            $this->release('v1.9.9'),
        ];

        $result = $this->resolver()->resolve($releases);

        $this->assertInstanceOf(Release::class, $result);
        $this->assertSame('1.10.0', $result->version);
    }

    public function testABackportDoesNotHideANewerRelease(): void
    {
        $releases = [
            $this->release('v1.0.5'),
            $this->release('v2.0.0'),
        ];

        $result = $this->resolver()->resolve($releases);

        $this->assertInstanceOf(Release::class, $result);
        $this->assertSame('2.0.0', $result->version);
    }

    public function testAnAssetThatDoesNotMatchThePatternIsAMismatch(): void
    {
        $release = $this->release('v1.0.0', ['assets' => [$this->asset('other-plugin-1.0.0.zip')]]);

        $this->assertSame(Outcome::ASSET_MISMATCH, $this->resolver()->resolve([$release]));
    }

    public function testTheMatchIsCaseSensitive(): void
    {
        $release = $this->release('v1.0.0', ['assets' => [$this->asset('My-Plugin-1.0.0.zip')]]);

        $this->assertSame(Outcome::ASSET_MISMATCH, $this->resolver()->resolve([$release]));
    }

    public function testAReleaseWithNoAssetsIsAMismatch(): void
    {
        $missing = $this->release('v1.0.0');
        unset($missing['assets']);

        $this->assertSame(Outcome::ASSET_MISMATCH, $this->resolver()->resolve([$this->release('v1.0.0', ['assets' => []])]));
        $this->assertSame(Outcome::ASSET_MISMATCH, $this->resolver()->resolve([$missing]));
        $this->assertSame(Outcome::ASSET_MISMATCH, $this->resolver()->resolve([$this->release('v1.0.0', ['assets' => 'none'])]));
    }

    public function testTwoMatchingAssetsAreAMismatch(): void
    {
        $release = $this->release('v1.0.0', ['assets' => [
            $this->asset('my-plugin-1.0.0.zip'),
            $this->asset('my-plugin-1.0.0-copy.zip', ['id' => 43]),
        ]]);

        $this->assertSame(Outcome::ASSET_MISMATCH, $this->resolver()->resolve([$release]));
    }

    public function testAssetsThatDoNotMatchAreIgnoredWhenExactlyOneDoes(): void
    {
        $release = $this->release('v1.0.0', ['assets' => [
            $this->asset('checksums.txt', ['id' => 41]),
            $this->asset('my-plugin-1.0.0.zip', ['id' => 42]),
        ]]);

        $result = $this->resolver()->resolve([$release]);

        $this->assertInstanceOf(Release::class, $result);
        $this->assertSame(42, $result->assetId);
    }

    public function testTheResolverNeverFallsBackToAnOlderRelease(): void
    {
        $releases = [
            $this->release('v1.1.0', ['assets' => []]),
            $this->release('v1.0.0'),
        ];

        $this->assertSame(Outcome::ASSET_MISMATCH, $this->resolver()->resolve($releases));
    }

    public function testAnAssetMissingTheDetailsThatTheUpdaterRequiresIsABadResponse(): void
    {
        foreach (['id', 'url', 'size'] as $key) {
            $asset = $this->asset('my-plugin-1.0.0.zip');
            unset($asset[$key]);

            $result = $this->resolver()->resolve([$this->release('v1.0.0', ['assets' => [$asset]])]);

            $this->assertSame(Outcome::BAD_RESPONSE, $result, $key);
        }
    }

    public function testThePatternAcceptsWildcards(): void
    {
        $resolver = new ReleaseResolver('front-desk-crm-*.zip');
        $release = $this->release('v3.4.5', ['assets' => [$this->asset('front-desk-crm-3.4.5.zip')]]);

        $result = $resolver->resolve([$release]);

        $this->assertInstanceOf(Release::class, $result);
        $this->assertSame('front-desk-crm-3.4.5.zip', $result->assetName);
    }
}
