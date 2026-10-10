<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Internal\Release;

/**
 * @internal
 */
final class Release
{
    /**
     * @param string $tag The tag as published, such as `v1.2.3`.
     * @param string $version The tag without the leading `v`, such as `1.2.3`.
     * @param string $notes The release notes, as Markdown. Empty when there are
     *     none.
     * @param string $publishedAt When GitHub published the release, as GitHub
     *     wrote it.
     * @param string $htmlUrl The address of the release page on GitHub.
     * @param int $assetId The ID of the asset on GitHub.
     * @param string $assetName The file name of the asset.
     * @param string $assetUrl The API address that downloads the asset.
     * @param int $assetSize The size of the asset in bytes.
     * @param string $assetDigest The digest GitHub reports for the asset, such
     *     as `sha256:` followed by the hash. Empty when GitHub doesn't report
     *     one.
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
     * @return array<string, int|string>
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

    /**
     * @param mixed $data The stored value.
     *
     * @return self|null The release, or null if the value isn't a release that
     *     this library can read.
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
}
