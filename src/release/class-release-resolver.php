<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Internal\Release;

/**
 * @internal
 */
final class ReleaseResolver
{
    private const TAG_PATTERN = '/^v((?:0|[1-9]\d*)\.(?:0|[1-9]\d*)\.(?:0|[1-9]\d*))$/';

    /**
     * @param string $assetPattern The pattern that an asset name must match,
     *     such as `front-desk-crm-*.zip`. `*` matches any run of characters and
     *     `?` matches one character. The match is case sensitive.
     */
    public function __construct(private readonly string $assetPattern)
    {
    }

    /**
     * @param array<mixed> $releases The decoded list of releases from GitHub.
     *
     * @return Release|string The release, or one of the `Outcome` codes:
     *     `NO_RELEASE` when no release meets those conditions, `ASSET_MISMATCH`
     *     when the highest release doesn't have exactly one matching asset, and
     *     `BAD_RESPONSE` when its asset is missing details that the updater
     *     requires.
     */
    public function resolve(array $releases): Release|string
    {
        $best = null;
        $bestVersion = '';

        foreach ($releases as $candidate) {
            $version = $this->versionOf($candidate);

            if ($version === null) {
                continue;
            }

            if ($best === null || version_compare($version, $bestVersion, '>')) {
                $best = $candidate;
                $bestVersion = $version;
            }
        }

        if ($best === null) {
            return Outcome::NO_RELEASE;
        }

        $asset = $this->matchingAsset($best['assets'] ?? null);

        if ($asset === null) {
            return Outcome::ASSET_MISMATCH;
        }

        $release = Release::fromArray([
            'tag' => $best['tag_name'],
            'version' => $bestVersion,
            'notes' => is_string($best['body'] ?? null) ? $best['body'] : '',
            'published_at' => is_string($best['published_at'] ?? null) ? $best['published_at'] : '',
            'html_url' => is_string($best['html_url'] ?? null) ? $best['html_url'] : '',
            'asset_id' => $asset['id'] ?? null,
            'asset_name' => $asset['name'],
            'asset_url' => $asset['url'] ?? null,
            'asset_size' => $asset['size'] ?? null,
            'asset_digest' => is_string($asset['digest'] ?? null) ? $asset['digest'] : '',
        ]);

        return $release ?? Outcome::BAD_RESPONSE;
    }


    private function versionOf(mixed $candidate): ?string
    {
        if (!is_array($candidate)) {
            return null;
        }

        if (($candidate['draft'] ?? null) !== false || ($candidate['prerelease'] ?? null) !== false) {
            return null;
        }

        $tag = $candidate['tag_name'] ?? null;

        if (!is_string($tag) || preg_match(self::TAG_PATTERN, $tag, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    /**
     * @return array<mixed>|null
     */
    private function matchingAsset(mixed $assets): ?array
    {
        if (!is_array($assets)) {
            return null;
        }

        $matches = [];

        foreach ($assets as $asset) {
            if (is_array($asset) && is_string($asset['name'] ?? null) && fnmatch($this->assetPattern, $asset['name'])) {
                $matches[] = $asset;
            }
        }

        return count($matches) === 1 ? $matches[0] : null;
    }
}
