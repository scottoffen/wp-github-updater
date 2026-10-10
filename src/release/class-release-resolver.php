<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Internal\Release;

/**
 * A resolver for determining the release to install from a list of releases.
 *
 * @internal
 */
final class ReleaseResolver
{
    /**
     * The pattern for a tag: a lowercase `v` followed by three numbers with no
     * leading zeros. The match captures the version, which is the tag without
     * the `v`.
     */
    private const TAG_PATTERN = '/^v((?:0|[1-9]\d*)\.(?:0|[1-9]\d*)\.(?:0|[1-9]\d*))$/';

    /**
     * @param string $assetPattern The pattern that the name of an asset must
     *     match. The name is matched with the `fnmatch()` function, in which
     *     `*` matches any run of characters, `?` matches one character, and a
     *     set of characters in square brackets, such as `[0-9]`, matches one
     *     character from the set. The match is case sensitive.
     */
    public function __construct(private readonly string $assetPattern)
    {
    }

    /**
     * Determines the release with the highest version from the releases that
     * can be used.
     *
     * A release can be used when it isn't a draft, isn't a pre-release, and
     * has a tag that matches the tag pattern, such as `v1.2.3`. The versions
     * are compared with the `version_compare()` function. Only the release
     * with the highest version is checked for an asset, and it must have
     * exactly one asset with a name that matches the asset pattern.
     *
     * @param array<mixed> $releases The decoded list of releases.
     *
     * @return Release|string The release, or one of the `Outcome` codes:
     *     `NO_RELEASE` when no release can be used, `ASSET_MISMATCH` when the
     *     highest release doesn't have exactly one asset with a matching name,
     *     and `BAD_RESPONSE` when the details of the asset don't meet the
     *     requirements of the `Release::fromArray()` method.
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
     * Determines the version of a release, when the release can be used.
     *
     * @param mixed $candidate The release to check.
     *
     * @return string|null The version, which is the tag without the leading
     *     `v`, or null when the release can't be used. The conditions for a
     *     release that can be used are in the description of the `resolve()`
     *     method.
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
     * Determines the asset with a name that matches the asset pattern from a
     * list of assets.
     *
     * @param mixed $assets The assets of a release.
     *
     * @return array<mixed>|null The asset, or null when `$assets` isn't an
     *     array or the number of assets with a matching name isn't one.
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
