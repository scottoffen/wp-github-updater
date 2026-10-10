<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Release;

/**
 * Picks the release to install from the list GitHub returns.
 *
 * A release can be installed when it is published, is not a prerelease, has a
 * tag of `v` followed by three numbers with no suffix, and is the highest
 * version among the releases that qualify. The list comes back in the order
 * GitHub created the releases, so the resolver compares versions and doesn't
 * trust the order.
 *
 * The resolver never falls back to an older release. If the highest version
 * has no usable asset, that is a publishing mistake, and the resolver reports
 * it instead of hiding it behind an older update.
 *
 * It reads no WordPress state and makes no requests.
 */
final class ReleaseResolver
{
    private const TAG_PATTERN = '/^v((?:0|[1-9]\d*)\.(?:0|[1-9]\d*)\.(?:0|[1-9]\d*))$/';

    /**
     * @param string $assetPattern The pattern an asset name must match, such
     *     as `front-desk-crm-*.zip`. `*` matches any run of characters and
     *     `?` matches one character. The match is case sensitive.
     */
    public function __construct(private readonly string $assetPattern)
    {
    }

    /**
     * Chooses the release to install.
     *
     * The highest qualifying release must have exactly one asset whose name
     * matches the pattern. Other assets are ignored.
     *
     * @param array<mixed> $releases The decoded list of releases from GitHub.
     *
     * @return Release|string The release, or one of the `Outcome` codes:
     *     `NO_RELEASE` when nothing qualifies, `ASSET_MISMATCH` when the
     *     highest release doesn't have exactly one matching asset, and
     *     `BAD_RESPONSE` when its asset is missing details the updater needs.
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

    /**
     * Returns the version of a release that qualifies, or null.
     *
     * A release must say that it is neither a draft nor a prerelease. A
     * missing or odd value counts as not qualifying.
     */
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
     * Returns the one asset whose name matches the pattern, or null when no
     * asset matches or more than one does.
     *
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
