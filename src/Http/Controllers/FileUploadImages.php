<?php

declare(strict_types=1);

namespace MetaFramework\Mediaclass\Http\Controllers;

use Exception;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use MetaFramework\Mediaclass\Contracts\MediaclassInterface;
use MetaFramework\Mediaclass\Cropable;
use MetaFramework\Mediaclass\Mediaclass;
use MetaFramework\Mediaclass\Models\Media;
use MetaFramework\Mediaclass\Support\Config as MediaclassConfig;
use MetaFramework\Mediaclass\Support\Path;
use MetaFramework\Mediaclass\Support\Subgroups;
use MetaFramework\Support\Traits\Responses;
use ReflectionClass;
use Throwable;

class FileUploadImages
{
    use Responses;

    protected MediaclassInterface $model;

    protected array $dimensions;

    protected array $urls = [];

    protected string $mime_type;

    protected string $filename;

    protected ?object $uploadedFile = null;

    private ?Media $media = null;

    private ?string $temp;

    private ?int $model_id;

    private string $folder_name = '';

    private string $media_group;

    private bool $is_ghost = false;

    private array $storables = [];

    private ?string $storedMime = null;

    private ?string $storedOriginalFilename = null;

    private Filesystem $disk;

    private bool $dimensionWarningAdded = false;

    public function __construct(private readonly Mediaclass $mediaclass)
    {
        $this->enableAjaxMode();
        $this->model_id    = (int)request('model_id') ?: null;
        $this->temp        = request('mediaclass_temp_id') ?: null;
        $this->media_group = request('group') ?: MediaclassConfig::defaultGroup();
        $this->is_ghost    = request('ghost') === '1';
        $this->storables   = (array)request('storables');

        $this->response['filetype'] = 'image';

        $this->disk = MediaclassConfig::getDisk();

    }

    public function getStorables(): ?array
    {
        return $this->storables ?: null;
    }

    public function setModel(?string $model = null): static
    {
        if (!$model) {
            $this->responseError(__('mfw-mediaclass.errors.missing_model'));

            return $this;
        }

        try {
            $this->model = (new ReflectionClass($model))->newInstance();

            if ($this->model_id && !$this->is_ghost) {
                $this->model = $this->model->find($this->model_id);
                if (!$this->model) {
                    $this->responseError(__('mfw-mediaclass.errors.missing_model'));

                    return $this;
                }
            }

            // For ghost models, use just the model name folder
            if ($this->is_ghost) {
                $this->folder_name = Str::snake((new ReflectionClass($this->model))->getShortName());
            } else {
                $this->folder_name = Path::mediaFolderName($this->model);
            }
        } catch (Throwable $e) {
            $this->responseException($e, 'Unknown ' . $model . ' class in ' . static::class);
        }

        return $this;
    }

    /**
     * Deletes a Mediaclass record and all its files
     * Deletes the relative directory if it is empty
     *
     * @return $this
     */
    public function delete(): static
    {
        if ($this->responseHasErrors()) {
            return $this;
        }

        if (!$this->is_ghost && !$this->model_id) {
            $this->responseError(__('mfw-mediaclass.errors.missing_model'));

            return $this;
        }

        try {
            $media = $this->mediaDescriptionQuery()
                ->whereKey((int)request('id'))
                ->first();

            if (!$media instanceof Media) {
                $this->responseError(__('mfw-mediaclass.errors.mediaDeleteFailed'));

                return $this;
            }

            $media->setRelation('model', $this->model);

            if (!$media->isExternalUrl()) {
                $path = Path::mediaFolderForMedia($media);
                $files = $this->mediaFiles($media);

                if ($files !== [] && !$this->disk->delete($files)) {
                    $this->responseError(__('mfw-mediaclass.errors.mediaDeleteFailed'));

                    return $this;
                }

                if ($this->disk->files($path) === []) {
                    $this->disk->deleteDirectory($path);
                }
            }

            $media->delete();
            $this->responseSuccess(__('mfw-mediaclass.notices.media_deleted'));
            $this->responseElement('deleted_id', $media->id);
        } catch (Throwable $e) {
            $this->responseException($e);
            report($e);
        }

        return $this;
    }

    /**
     * @return array<int, string>
     */
    private function mediaFiles(Media $media): array
    {
        $folder = Path::mediaFolderForMedia($media);
        $filename = preg_quote((string)$media->filename, '/');

        return collect($this->disk->files($folder))
            ->filter(static function (string $path) use ($filename): bool {
                $basename = pathinfo($path, PATHINFO_FILENAME);

                return preg_match('/(?:^|_)' . $filename . '(?:_|$)/', $basename) === 1;
            })
            ->values()
            ->all();
    }

    public function deleteBridge(): static
    {
        if ($this->responseHasErrors()) {
            return $this;
        }

        if (!method_exists($this->model, 'deleteMediaclassBridgeMedia')) {
            $this->responseError(__('mfw-mediaclass.errors.bridgeDeleteUnsupported'));

            return $this;
        }

        try {
            $deleted = $this->model->deleteMediaclassBridgeMedia(
                (string)request('id'),
                (string)request('group', $this->media_group),
                request('subgroup') ? (string)request('subgroup') : null,
            );

            if (!$deleted) {
                $this->responseError(__('mfw-mediaclass.errors.bridgeDeleteFailed'));

                return $this;
            }

            $this->responseSuccess(__('mfw-mediaclass.notices.bridge_deleted'));
            $this->responseElement('deleted_id', (string)request('id'));
        } catch (Throwable $e) {
            $this->responseException($e);
        }

        return $this;
    }

    public function saveDescriptions(): static
    {
        if ($this->responseHasErrors()) {
            return $this;
        }

        if (!$this->is_ghost && !$this->model_id) {
            $this->responseError(__('mfw-mediaclass.errors.missing_model'));

            return $this;
        }

        try {
            $nativeCount = $this->saveNativeMediaDetails((array)request('mediaclass'));
            $bridgeCount = $this->saveBridgeDescriptions((array)request('mediaclass_bridge'));

            $this->responseSuccess(__('mfw-mediaclass.notices.media_details_saved'));
            $this->responseElement('updated_count', $nativeCount + $bridgeCount);
        } catch (Throwable $e) {
            $this->responseException($e, __('mfw-mediaclass.errors.mediaDetailsSaveFailed'));
        }

        return $this;
    }

    public function saveSubgroup(): static
    {
        if ($this->responseHasErrors()) {
            return $this;
        }

        if (!$this->is_ghost && !$this->model_id) {
            $this->responseError(__('mfw-mediaclass.errors.missing_model'));

            return $this;
        }

        $validator = Validator::make(request()->all(), [
            'media_id' => ['required', 'integer'],
            'subgroup' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            $this->responseError($validator->errors()->first());

            return $this;
        }

        $options = Subgroups::options($this->model, $this->media_group);

        if ($options === []) {
            $this->responseError(__('mfw-mediaclass.errors.subgroupUnsupported'));

            return $this;
        }

        $subgroup = trim((string)request('subgroup', ''));

        if ($subgroup !== '' && !array_key_exists($subgroup, $options)) {
            $this->responseError(__('mfw-mediaclass.errors.subgroupInvalid'));

            return $this;
        }

        try {
            $media = Subgroups::mediaQuery($this->model, $this->media_group, $this->is_ghost)
                ->whereKey((int)request('media_id'))
                ->first();

            if (!$media instanceof Media) {
                $this->responseError(__('mfw-mediaclass.errors.missing_model'));

                return $this;
            }

            $media->subgroup = $subgroup !== '' ? $subgroup : null;
            $media->save();

            $this->responseSuccess(__('mfw-mediaclass.notices.subgroup_saved'));
            $this->responseElement('media_id', $media->id);
            $this->responseElement('group', $this->media_group);
            $this->responseElement('subgroup', $media->subgroup);
            $this->responseElement('uses_subgroups', Subgroups::active($this->model, $this->media_group, $this->is_ghost));
        } catch (Throwable $e) {
            $this->responseException($e, __('mfw-mediaclass.errors.subgroupSaveFailed'));
        }

        return $this;
    }

    public function reorder(): static
    {
        if ($this->responseHasErrors()) {
            return $this;
        }

        if (!$this->is_ghost && !$this->model_id) {
            $this->responseError(__('mfw-mediaclass.errors.missing_model'));

            return $this;
        }

        $validator = Validator::make(request()->all(), [
            'media_ids' => ['required', 'array', 'min:1'],
            'media_ids.*' => ['required', 'integer', 'distinct'],
        ]);

        if ($validator->fails()) {
            $this->responseError($validator->errors()->first());

            return $this;
        }

        $mediaIds = array_map('intval', array_values($validator->validated()['media_ids']));

        try {
            $changed = DB::transaction(function () use ($mediaIds): ?bool {
                $query = Subgroups::mediaQuery($this->model, $this->media_group, $this->is_ghost);
                $currentIds = (clone $query)
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->pluck('id')
                    ->map(fn (mixed $id): int => (int)$id)
                    ->all();

                $expectedIds = $currentIds;
                $submittedIds = $mediaIds;
                sort($expectedIds);
                sort($submittedIds);

                if ($submittedIds !== $expectedIds) {
                    return null;
                }

                if ($mediaIds === $currentIds) {
                    return false;
                }

                $sortOrderCase = collect($mediaIds)
                    ->map(
                        fn (int $mediaId, int $index): string => sprintf(
                            'WHEN %d THEN %d',
                            $mediaId,
                            $index + 1,
                        ),
                    )
                    ->implode(' ');

                (clone $query)
                    ->whereIn('id', $mediaIds)
                    ->update([
                        'sort_order' => DB::raw("CASE id {$sortOrderCase} END"),
                        'position' => 'left',
                        'subgroup' => null,
                        'updated_at' => now(),
                    ]);

                return true;
            });

            if ($changed === null) {
                $this->responseError(__('mfw-mediaclass.errors.reorderInvalid'));

                return $this;
            }

            if ($changed) {
                $this->responseSuccess(__('mfw-mediaclass.notices.order_saved'));
            }

            $this->responseElement('changed', $changed);
            $this->responseElement('media_ids', $mediaIds);
            $this->responseElement('group', $this->media_group);
            $this->responseElement('uses_subgroups', false);
        } catch (Throwable $e) {
            $this->responseException($e, __('mfw-mediaclass.errors.reorderFailed'));
        }

        return $this;
    }

    public function upload(): static
    {
        if ($this->responseHasErrors()) {
            return $this;
        }

        $files = request()->file('files');

        $this->filename = Str::random(6);
        $this->uploadedFile = is_array($files) ? ($files[0] ?? null) : $files;

        if ($this->hasErrors()) {
            return $this;
        }

        // Early dimension check for images (non-SVG)
        if (strstr($this->uploadedFile->getMimeType(), '/', true) == 'image'
            &&
            !str_contains($this->uploadedFile->getMimeType(), 'svg')
        ) {
            $required = MediaclassConfig::getGroupRequiredDimensions($this->model, $this->media_group);

            if ($required) {
                [$requiredWidth, $requiredHeight] = $required;

                // Get image dimensions
                $imageInfo = @getimagesize($this->uploadedFile->getPathname());

                if ($imageInfo) {
                    $imageWidth  = $imageInfo[0];
                    $imageHeight = $imageInfo[1];

                    if ($imageWidth < $requiredWidth || $imageHeight < $requiredHeight) {
                        if ($this->handleDimensionMismatch($requiredWidth, $requiredHeight, $imageWidth, $imageHeight)) {
                            return $this;
                        }
                    }
                }
            }
        }

        // documents
        if (strstr($this->uploadedFile->getMimeType(), '/', true) != 'image') {
            // Move non-image files
            // TODO: controls
            return $this->uploadFiles();
        }

        $this->response['has_positions'] = (bool)request('positions');

        // svg
        if (str_contains($this->uploadedFile->getMimeType(), 'svg')) {
            return $this->uploadSvg();
        }

        if (strstr($this->uploadedFile->getMimeType(), '/', true) != 'image') {
            $this->responseAbort(__('mfw-mediaclass.errors.mustBeImage'));

            return $this;
        }

        return $this->processImage();
    }

    public function uploadUrl(): static
    {
        if ($this->responseHasErrors()) {
            return $this;
        }

        $validator = Validator::make(request()->all(), [
            'url' => ['required', 'url', 'starts_with:http://,https://'],
            'embed_width_mode' => ['nullable', 'in:pixels,full'],
            'embed_width' => ['nullable', 'integer', 'min:1', 'max:7680'],
            'embed_height' => ['nullable', 'integer', 'min:1', 'max:4320'],
        ]);

        if ($validator->fails()) {
            $this->responseError(__('mfw-mediaclass.errors.invalidUrl'));

            return $this;
        }

        $url = (string)$validator->validated()['url'];

        $this->filename = Str::random(6);
        $this->storedMime = 'video/url';
        $this->storedOriginalFilename = $url;
        $this->storables['url'] = $url;
        $this->storables['embed_width'] = request('embed_width_mode') === 'full'
            ? '100%'
            : Media::normalizeEmbedWidth(request('embed_width'));
        $this->storables['embed_height'] = Media::normalizeEmbedHeight(request('embed_height'));
        $thumbnail = $this->mediaclass->thumbnail($url);

        if ($thumbnail !== null) {
            $this->storables['thumbnail_url'] = $thumbnail;
        }

        $this->response['filetype'] = 'video';
        $this->response['filename'] = $this->filename;
        $this->response['link'] = $url;
        $this->response['preview'] = $thumbnail ?? asset('vendor/mfw-mediaclass/images/files/mov.png');
        $this->response['has_positions'] = (bool)request('positions');

        try {
            $this->media = $this->store();
        } catch (Throwable $e) {
            $this->responseException($e);
        }

        $this->mediaResponse();

        return $this;
    }

    private function uploadFiles(): static
    {
        if (!$this->is_ghost) {
            $this->folder_name = Path::mediaFolderName($this->model, true);
        }

        $this->response['filetype'] = 'file';
        $this->response['filename'] = $this->filename . '.' . $this->uploadedFile->guessExtension();
        $this->response['link']     = $this->disk->url($this->folder_name . '/' . $this->response['filename'] . '?' . time());
        $this->response['fileicon'] = asset('vendor/mfw-mediaclass/images/files/' . $this->uploadedFile->guessExtension() . '.png');
        $this->response['preview']  = $this->response['fileicon'];

        try {
            $this->media = $this->store();
        } catch (Throwable $e) {
            $this->responseException($e);
        }

        $this->uploadedFile->move($this->disk->path($this->folder_name), $this->response['filename']);

        $this->mediaResponse();

        return $this;
    }

    private function uploadSvg(): static
    {
        if (!$this->is_ghost) {
            $this->folder_name = Path::mediaFolderName($this->model, true);
        }

        $this->response['filename'] = $this->filename . '.svg';
        $file                       = $this->folder_name . '/' . $this->response['filename'];
        $img                        = $this->disk->url($file . '?' . time());
        $this->response['fileicon'] = asset('vendor/mfw-mediaclass/images/files/svg.png');

        try {
            $this->media = $this->store();
        } catch (Throwable $e) {
            $this->responseException($e);
        }

        $this->uploadedFile->move($this->disk->path($this->folder_name), $this->response['filename']);

        $this->responseElement('link', $this->disk->url($file));
        $this->responseElement('preview', $img);

        $this->mediaResponse();

        return $this;
    }

    private function processImage(): static
    {
        // Get group settings if available
        $groupSettings = MediaclassConfig::getGroupSettings($this->model, $this->media_group);

        // Load dimensions from group settings or default (largest -> smallest)
        $this->dimensions = MediaclassConfig::getGroupResizeDimensions($this->model, $this->media_group);

        $imageInfo = @getimagesize($this->uploadedFile->getPathname());

        if (!$imageInfo) {
            $this->responseError(__('mfw-mediaclass.errors.mustBeImage'));

            return $this;
        }

        $imageWidth = (int)$imageInfo[0];
        $imageHeight = (int)$imageInfo[1];

        // Check dimensions if group settings exist
        if (!empty($groupSettings)) {
            $required = MediaclassConfig::getGroupRequiredDimensions($this->model, $this->media_group);
            $requiredWidth = $required ? $required[0] : null;
            $requiredHeight = $required ? $required[1] : null;

            // Validate dimensions
            if ($requiredWidth && $requiredHeight) {
                if ($imageWidth < $requiredWidth || $imageHeight < $requiredHeight) {
                    if ($this->handleDimensionMismatch($requiredWidth, $requiredHeight, $imageWidth, $imageHeight)) {
                        return $this;
                    }
                }

                // NEW: Check if cropable is enabled and validate scale
                if (isset($groupSettings['cropable']) && $groupSettings['cropable'] === true) {
                    // Calculate what the resized dimensions would be
                    $isWidthMain          = $requiredWidth >= $requiredHeight;
                    $mainDimension        = $isWidthMain ? $requiredWidth : $requiredHeight;
                    $currentMainDimension = $isWidthMain ? $imageWidth : $imageHeight;

                    // Only check scale if resizing will happen
                    if ($currentMainDimension !== $mainDimension) {
                        $scaleRatio    = $mainDimension / $currentMainDimension;
                        $resizedWidth  = (int)($imageWidth * $scaleRatio);
                        $resizedHeight = (int)($imageHeight * $scaleRatio);

                        // Check if resized dimensions would be insufficient for cropping
                        if ($resizedWidth < $requiredWidth || $resizedHeight < $requiredHeight) {
                            // Calculate the minimum scale needed
                            $minScaleWidth  = $requiredWidth / $imageWidth;
                            $minScaleHeight = $requiredHeight / $imageHeight;
                            $minScale       = max($minScaleWidth, $minScaleHeight);

                            // Calculate minimum original dimensions needed
                            $minOriginalWidth  = (int)ceil($requiredWidth / $minScale);
                            $minOriginalHeight = (int)ceil($requiredHeight / $minScale);

                            if ($this->handleDimensionMismatch(
                                $requiredWidth,
                                $requiredHeight,
                                $imageWidth,
                                $imageHeight,
                                'scale_for_crop',
                                [
                                    'width'           => $requiredWidth,
                                    'height'          => $requiredHeight,
                                    'min_width'       => $minOriginalWidth,
                                    'min_height'      => $minOriginalHeight,
                                    'uploaded_width'  => $imageWidth,
                                    'uploaded_height' => $imageHeight,
                                ],
                            )) {
                                return $this;
                            }
                        }
                    }
                }
            }
        }

        $this->urls = [];

        $mimeType = $this->uploadedFile->getMimeType();

        $this->mime_type            = (str_contains($mimeType, 'png') ? 'png' : 'jpg');
        $this->response['fileicon'] = asset('vendor/mfw-mediaclass/images/files/jpg.png');

        $ratio                   = ($imageWidth / $imageHeight) > 1 ? 'h' : 'v';
        $this->response['ratio'] = $ratio;

        $isSingleGroupSize = !empty($groupSettings)
            && empty($groupSettings['sizes'])
            && isset($groupSettings['width'], $groupSettings['height']);

        foreach ($this->dimensions as $key => $dimensions) {
            $file = Path::mediaFilePath(
                $this->model,
                $this->filename,
                $this->mime_type,
                (string)$key,
                (int)$dimensions['width'],
                createAccessKey: true,
            );

            $targetWidth  = $dimensions['width'];
            $targetHeight = $dimensions['height'];

            [$newWidth, $newHeight] = $this->targetImageDimensions(
                $imageWidth,
                $imageHeight,
                (int)$targetWidth,
                (int)$targetHeight,
                $isSingleGroupSize,
            );

            $encodedImage = $this->encodeResizedImage($newWidth, $newHeight);

            if ($encodedImage === null) {
                return $this;
            }

            $this->disk->put($file, $encodedImage);

            $this->urls[$key] = $this->disk->url($file . '?' . time());

            unset($encodedImage);
        }

        $this->responseElement('link', $this->urls[array_key_first($this->urls)] ?? MediaclassConfig::defaultImgUrl());
        $this->responseElement('preview', $this->urls[array_key_last($this->urls)] ?? MediaclassConfig::defaultImgUrl());
        $this->responseElement('urls', $this->urls);

        try {
            $this->media = $this->store();
        } catch (Throwable $e) {
            $this->responseException($e);
        }

        if (!$this->media) {
            return $this;
        }

        // Inject the current model instance for response rendering and path resolution.
        $this->media->setRelation('model', $this->model);

        $cropable = new Cropable($this->media);

        // Handle cropable settings
        $cropableData = request('cropable');

        // If no cropable data from request but group has cropable setting
        if (!$cropableData && !empty($groupSettings)) {
            $required = MediaclassConfig::getGroupRequiredDimensions($this->model, $this->media_group);
            $requiredWidth = $required ? $required[0] : null;
            $requiredHeight = $required ? $required[1] : null;

            // Check if image dimensions match exactly
            $isExactMatch = ($imageWidth == $requiredWidth && $imageHeight == $requiredHeight);

            // Only set cropable if cropable is true AND dimensions don't match exactly
            if (isset($groupSettings['cropable'])) {
                if (is_array($groupSettings['cropable'])) {
                    $cropableData = $groupSettings['cropable'];
                } elseif ($groupSettings['cropable'] === true && !$isExactMatch) {
                    // Set cropable dimensions from group settings
                    $label        = $groupSettings['label'] ?? ucfirst($this->media_group);
                    $cropableData = [
                        $this->media_group => [
                            $requiredWidth,
                            $requiredHeight,
                        ],
                    ];
                }
            }
        }

        if (is_string($cropableData)) {
            // Try to decode if it's JSON
            $decoded = json_decode($cropableData, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $cropableData = $decoded;
            }
        }

        $cropable->setCropableFromComponent($cropableData);

        $this->responseElement('cropable_links', $cropable->links());
        $this->responseElement('cropable_settings', json_encode($cropable->getCropableSettings()));

        $this->mediaResponse();

        return $this;
    }

    /**
     * @throws Exception
     */
    private function store(): Media
    {
        if (config()->has('app.cacheables') && in_array($this->model->type, (array)config('app.cacheables'))) {
            cache()->forget($this->model->type);
        }

        $morphable = Relation::morphMap() ? array_key_first(array_filter(Relation::morphMap(), fn ($item) => $item == get_class($this->model))) : get_class($this->model);
        $path      = Path::mediaFolderName($this->model);

        if (!$morphable) {
            File::delete(File::glob($this->disk->path($path . DIRECTORY_SEPARATOR . '*' . $this->filename . '*')));
            throw new Exception('Invalid Media morphable');
        }

        return Media::query()->create([
            'model_type'        => $morphable,
            'model_id'          => $this->is_ghost ? null : $this->model_id,
            'group'             => $this->media_group,
            'subgroup'          => request('subgroup') ?: null,
            'description'       => request('description'),
            'position'          => request('position') ?: 'left',
            'sort_order'        => $this->nextSortOrder(),
            'mime'              => $this->storedMime ?? $this->uploadedFile?->getMimeType(),
            'original_filename' => $this->storedOriginalFilename ?? $this->uploadedFile?->getClientOriginalName(),
            'filename'          => $this->filename,
            'temp'              => $this->is_ghost ? null : $this->temp,
            'storable'          => $this->getStorables(),
        ]);
    }

    private function nextSortOrder(): int
    {
        $query = Subgroups::mediaQuery($this->model, $this->media_group, $this->is_ghost);

        if (!$this->is_ghost && !$this->model_id && $this->temp) {
            $query->where('temp', $this->temp);
        }

        return ((int)$query->max('sort_order')) + 1;
    }

    private function mediaResponse(): static
    {
        $this->responseElement('uploaded', $this->media);
        $this->responseElement('temp', $this->temp);
        $this->responseElement('count_files', request('count_files'));

        return $this;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function targetImageDimensions(
        int $imageWidth,
        int $imageHeight,
        int $targetWidth,
        int $targetHeight,
        bool $isSingleGroupSize,
    ): array {
        if ($isSingleGroupSize) {
            $isWidthMain = $targetWidth >= $targetHeight;
            $mainDimension = $isWidthMain ? $targetWidth : $targetHeight;
            $currentMainDimension = $isWidthMain ? $imageWidth : $imageHeight;

            if ($currentMainDimension === $mainDimension) {
                return [$imageWidth, $imageHeight];
            }

            $scaleRatio = $mainDimension / $currentMainDimension;

            return [
                max(1, (int)($imageWidth * $scaleRatio)),
                max(1, (int)($imageHeight * $scaleRatio)),
            ];
        }

        $widthRatio = $targetWidth / $imageWidth;
        $scaleRatio = min($widthRatio, 1);

        return [
            max(1, (int)($imageWidth * $scaleRatio)),
            max(1, (int)($imageHeight * $scaleRatio)),
        ];
    }

    private function encodeResizedImage(int $width, int $height): ?string
    {
        $source = $this->createSourceImage();

        if (!$source instanceof \GdImage) {
            $this->responseError(__('mfw-mediaclass.errors.upload_failed'));

            return null;
        }

        $resized = $this->resizeSourceImage($source, $width, $height);

        if (!$resized instanceof \GdImage) {
            $this->responseError(__('mfw-mediaclass.errors.upload_failed'));

            return null;
        }

        ob_start();

        $encoded = $this->mime_type === 'png'
            ? imagepng($resized)
            : imagejpeg($resized, null, 75);

        $contents = ob_get_clean();

        unset($source, $resized);

        if (!$encoded || !is_string($contents)) {
            $this->responseError(__('mfw-mediaclass.errors.upload_failed'));

            return null;
        }

        return $contents;
    }

    private function createSourceImage(): \GdImage|false
    {
        return $this->mime_type === 'png'
            ? imagecreatefrompng($this->uploadedFile->getPathname())
            : imagecreatefromjpeg($this->uploadedFile->getPathname());
    }

    private function resizeSourceImage(\GdImage $source, int $width, int $height): \GdImage|false
    {
        if (imagesx($source) === $width && imagesy($source) === $height) {
            return $source;
        }

        $resized = imagecreatetruecolor($width, $height);

        if (!$resized instanceof \GdImage) {
            return false;
        }

        if ($this->mime_type === 'png') {
            imagealphablending($resized, false);
            imagesavealpha($resized, true);

            $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);

            if ($transparent !== false) {
                imagefill($resized, 0, 0, $transparent);
            }
        }

        imagecopyresampled(
            $resized,
            $source,
            0,
            0,
            0,
            0,
            $width,
            $height,
            imagesx($source),
            imagesy($source),
        );

        return $resized;
    }

    private function saveNativeMediaDetails(array $medias): int
    {
        if ($medias === []) {
            return 0;
        }

        $updated = 0;
        $query = $this->mediaDescriptionQuery();

        foreach ($medias as $mediaId => $mediaInput) {
            if (!is_numeric($mediaId) || !is_array($mediaInput)) {
                continue;
            }

            $media = (clone $query)
                ->whereKey((int)$mediaId)
                ->first();

            if (!$media instanceof Media) {
                continue;
            }

            $hasDescription = array_key_exists('description', $mediaInput);
            $hasEmbedDimensions = $media->isVideo() && (
                array_key_exists('embed_width_mode', $mediaInput)
                || array_key_exists('embed_width', $mediaInput)
                || array_key_exists('embed_height', $mediaInput)
            );

            if (!$hasDescription && !$hasEmbedDimensions) {
                continue;
            }

            if ($hasDescription) {
                $media->description = $this->normalizeDescriptions((array)$mediaInput['description']);
            }

            if ($hasEmbedDimensions) {
                $storable = (array)($media->storable ?? []);
                $storable['embed_width'] = ($mediaInput['embed_width_mode'] ?? null) === 'full'
                    ? '100%'
                    : Media::normalizeEmbedWidth($mediaInput['embed_width'] ?? $media->embedWidth());
                $storable['embed_height'] = Media::normalizeEmbedHeight(
                    $mediaInput['embed_height'] ?? $media->embedHeight(),
                );
                $media->storable = $storable;
            }

            $media->save();

            $updated++;
        }

        return $updated;
    }

    private function saveBridgeDescriptions(array $bridgeMedia): int
    {
        $groupPayload = (array)($bridgeMedia[$this->media_group] ?? []);

        if ($groupPayload === [] || !method_exists($this->model, 'syncMediaclassBridgeMedia')) {
            return 0;
        }

        $payload = [];

        foreach ($groupPayload as $key => $bridgeInput) {
            if (!is_array($bridgeInput)) {
                continue;
            }

            $bridgeId = trim((string)($bridgeInput['id'] ?? ''));

            if ($bridgeId === '') {
                continue;
            }

            if (!array_key_exists('description', $bridgeInput)) {
                continue;
            }

            $payload[(string)$key] = [
                'id' => $bridgeId,
                'description' => $this->normalizeDescriptions((array)$bridgeInput['description']),
            ];
        }

        if ($payload === []) {
            return 0;
        }

        $this->model->syncMediaclassBridgeMedia([
            $this->media_group => $payload,
        ]);

        return count($payload);
    }

    private function mediaDescriptionQuery(): Builder
    {
        $morphable = Relation::morphMap()
            ? array_search(get_class($this->model), Relation::morphMap(), true) ?: get_class($this->model)
            : get_class($this->model);
        $subgroup = $this->requestedSubgroup();

        return Media::query()
            ->where('model_type', $morphable)
            ->where('group', $this->media_group)
            ->when(
                $this->is_ghost,
                fn ($query) => $query->whereNull('model_id'),
                fn ($query) => $query->where('model_id', $this->model_id),
            )
            ->when(
                $this->is_ghost && $this->temp,
                fn ($query) => $query->where('temp', $this->temp),
            )
            ->when($subgroup !== null, fn ($query) => $query->where('subgroup', $subgroup));
    }

    private function requestedSubgroup(): ?string
    {
        $subgroup = trim((string)request('subgroup', ''));

        return $subgroup !== '' && $subgroup !== 'false' ? $subgroup : null;
    }

    private function handleDimensionMismatch(
        int $requiredWidth,
        int $requiredHeight,
        int $imageWidth,
        int $imageHeight,
        string $errorKey = 'dimensions',
        array $parameters = [],
    ): bool {
        $parameters = array_merge([
            'width'           => $requiredWidth,
            'height'          => $requiredHeight,
            'uploaded_width'  => $imageWidth,
            'uploaded_height' => $imageHeight,
        ], $parameters);

        if (MediaclassConfig::shouldEnforceDimensions($this->model, $this->media_group)) {
            $this->responseError(__('mfw-mediaclass.errors.' . $errorKey, $parameters));

            return true;
        }

        if (!$this->dimensionWarningAdded) {
            $this->responseWarning(__('mfw-mediaclass.notices.dimension_warning', $parameters), false);
            $this->dimensionWarningAdded = true;
        }

        return false;
    }

    private function normalizeDescriptions(array $descriptions): array
    {
        return collect($descriptions)
            ->mapWithKeys(static function (mixed $description, int|string $locale): array {
                return [
                    (string)$locale => is_string($description) ? trim($description) : (string)$description,
                ];
            })
            ->all();
    }

    /**
     * Check if there are errors in the upload process
     * This should be added to FileUploadImages.php
     */
    private function hasErrors(): bool
    {
        // Check if file upload failed
        if (!$this->uploadedFile || !$this->uploadedFile->isValid()) {
            $this->responseError(__('mfw-mediaclass.errors.upload_failed'));

            return true;
        }

        // Check file size
        $file    = $this->uploadedFile;
        $maxSize = $this->calculateMaxFileSize(request('maxfilesize'));

        if ($file->getSize() > $maxSize) {
            $this->responseError(__('mfw-mediaclass.errors.maxFileSize') . ' ' . $this->formatBytes($maxSize));

            return true;
        }

        // Check file type
        $allowedTypes = ['image/jpeg', 'image/png', 'image/svg+xml', 'application/pdf'];
        if (!in_array($file->getMimeType(), $allowedTypes)) {
            $this->responseError(__('mfw-mediaclass.errors.acceptFileTypes'));

            return true;
        }

        return false;
    }

    private function responseHasErrors(): bool
    {
        return array_key_exists('error', $this->response) || array_key_exists('abort', $this->response);
    }

    /**
     * Format bytes to human-readable format
     */
    private function formatBytes(int|float $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];

        $bytes = max($bytes, 0);
        $pow   = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow   = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    /**
     * Calculate max file size from string format (e.g., "5MB", "500KB")
     */
    private function calculateMaxFileSize(?string $size): int
    {
        if (!$size) {
            return 16 * 1024 * 1024; // 16MB default
        }

        $size  = strtoupper(trim($size));
        $value = (int)preg_replace('/[^0-9]/', '', $size);

        return match (true) {
            str_contains($size, 'KB') => $value * 1024,
            str_contains($size, 'MB') => $value * 1024 * 1024,
            str_contains($size, 'GB') => $value * 1024 * 1024 * 1024,
            default => $value,
        };
    }
}
