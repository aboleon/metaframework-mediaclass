<?php

declare(strict_types=1);

namespace MetaFramework\Mediaclass\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Symfony\Component\Mime\MimeTypes;

class BridgeMedia
{
    /**
     * @param  array<string, string>  $urls
     * @param  array<string, string|null>  $description
     */
    public function __construct(
        public string $id,
        public string $group,
        public string $mime,
        public string $original_filename,
        public string $filename,
        public array $urls,
        public array $description = [],
        public string $position = 'left',
        public ?CarbonInterface $created_at = null,
        public ?string $subgroup = null,
    ) {
        $this->created_at ??= Carbon::now();
    }

    /**
     * @param  array{id?: string, group?: string, mime?: string, original_filename?: string, filename?: string, urls?: array<string, string>, description?: array<string, string|null>, position?: string, created_at?: CarbonInterface|string|null, subgroup?: string|null}  $media
     */
    public static function fromArray(array $media): self
    {
        $createdAt = $media['created_at'] ?? null;

        if (is_string($createdAt)) {
            $createdAt = Carbon::parse($createdAt);
        }

        return new self(
            id: (string) ($media['id'] ?? ''),
            group: (string) ($media['group'] ?? 'media'),
            mime: (string) ($media['mime'] ?? 'image/jpeg'),
            original_filename: (string) ($media['original_filename'] ?? $media['filename'] ?? ''),
            filename: (string) ($media['filename'] ?? $media['original_filename'] ?? ''),
            urls: (array) ($media['urls'] ?? []),
            description: (array) ($media['description'] ?? []),
            position: (string) ($media['position'] ?? 'left'),
            created_at: $createdAt instanceof CarbonInterface ? $createdAt : null,
            subgroup: isset($media['subgroup']) ? (string) $media['subgroup'] : null,
        );
    }

    public function key(): string
    {
        return preg_replace('/[^A-Za-z0-9_-]/', '_', $this->id) ?: md5($this->id);
    }

    public function domId(): string
    {
        return 'bridge_' . $this->key();
    }

    public function extension(): ?string
    {
        if ($this->isExternalUrl()) {
            return 'mov';
        }

        $extension = pathinfo($this->filename, PATHINFO_EXTENSION);

        if ($extension !== '') {
            return $extension;
        }

        return MimeTypes::getDefault()->getExtensions($this->mime)[0] ?? null;
    }

    public function url(string $size = 'sm', ?string $cropKey = null): string
    {
        if ($cropKey && isset($this->urls['cropped_' . $cropKey])) {
            return $this->urls['cropped_' . $cropKey];
        }

        if ($size === 'cropped' && isset($this->urls['cropped'])) {
            return $this->urls['cropped'];
        }

        if (isset($this->urls[$size])) {
            return $this->urls[$size];
        }

        if ($size === 'xl') {
            return $this->urls['original'] ?? $this->urls['sm'] ?? reset($this->urls) ?: '';
        }

        return $this->urls['sm'] ?? $this->urls['original'] ?? reset($this->urls) ?: '';
    }

    public function isImage(): bool
    {
        return str_contains($this->mime, 'image');
    }

    public function isVideo(): bool
    {
        return str_contains($this->mime, 'video');
    }

    public function isExternalUrl(): bool
    {
        return str_contains($this->mime, 'url');
    }
}
