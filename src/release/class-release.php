<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Internal\Release;

/**
 * A GitHub release, with the details of the asset that contains the update.
 *
 * @internal
 */
final class Release
{
    /**
     * @param string $tag The tag of the release, such as `v1.2.3`.
     * @param string $version The tag without the leading `v`, such as `1.2.3`.
     * @param string $notes The release notes, as Markdown.
     * @param string $publishedAt The time that the release was published.
     * @param string $htmlUrl The address of the release page on GitHub.
     * @param int $assetId The ID of the asset on GitHub.
     * @param string $assetName The file name of the asset.
     * @param string $assetUrl The GitHub API address of the asset.
     * @param int $assetSize The size of the asset in bytes.
     * @param string $assetDigest The digest of the asset, such as `sha256:`
     *     followed by the hash, or an empty string when there is none.
     */
    public function __construct(
        public readonly string $tag,
        public readonly string $version,
        public readonly string $notes,
        public readonly string $publishedAt,
        public readonly string $htmlUrl,
        public readonly int $assetId,
        public readonly string $assetName,
        public readonly string $assetUrl,
        public readonly int $assetSize,
        public readonly string $assetDigest,
    ) {
    }

    /**
     * Reads a release from an array.
     *
     * The array must have the keys that the `toArray()` method writes. The
     * `notes`, `published_at`, `html_url`, and `asset_digest` values must be
     * strings, and they can be empty. The `tag`, `version`, `asset_name`, and
     * `asset_url` values must be strings that aren't empty. The `asset_id`
     * value must be an integer of 1 or more, and the `asset_size` value must be
     * an integer of 0 or more.
     *
     * @param mixed $data The value to read.
     *
     * @return self|null The release, or null when the value isn't an array that
     *     meets those requirements.
     */
    public static function fromArray(mixed $data): ?self
    {
        if (!is_array($data)) {
            return null;
        }

        foreach (['tag', 'version', 'notes', 'published_at', 'html_url', 'asset_name', 'asset_url', 'asset_digest'] as $key) {
            if (!isset($data[$key]) || !is_string($data[$key])) {
                return null;
            }
        }

        foreach (['asset_id', 'asset_size'] as $key) {
            if (!isset($data[$key]) || !is_int($data[$key])) {
                return null;
            }
        }

        if (
            $data['tag'] === ''
            || $data['version'] === ''
            || $data['asset_name'] === ''
            || $data['asset_url'] === ''
            || $data['asset_id'] < 1
            || $data['asset_size'] < 0
        ) {
            return null;
        }

        return new self(
            $data['tag'],
            $data['version'],
            $data['notes'],
            $data['published_at'],
            $data['html_url'],
            $data['asset_id'],
            $data['asset_name'],
            $data['asset_url'],
            $data['asset_size'],
            $data['asset_digest'],
        );
    }

    /**
     * Returns an array of values that represent the release.
     *
     * @return array<string, int|string> The array that the `fromArray()` method
     *     reads, with the keys `tag`, `version`, `notes`, `published_at`,
     *     `html_url`, `asset_id`, `asset_name`, `asset_url`, `asset_size`, and
     *     `asset_digest`.
     */
    public function toArray(): array
    {
        return [
            'tag' => $this->tag,
            'version' => $this->version,
            'notes' => $this->notes,
            'published_at' => $this->publishedAt,
            'html_url' => $this->htmlUrl,
            'asset_id' => $this->assetId,
            'asset_name' => $this->assetName,
            'asset_url' => $this->assetUrl,
            'asset_size' => $this->assetSize,
            'asset_digest' => $this->assetDigest,
        ];
    }
}
