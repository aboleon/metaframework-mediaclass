<?php

namespace MetaFramework\Mediaclass\Controllers;

use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use MetaFramework\Mediaclass\Config;
use MetaFramework\Mediaclass\Cropable;
use MetaFramework\Mediaclass\Models\Media;
use MetaFramework\Mediaclass\Path;
use MetaFramework\Support\Traits\Ajax;
use MetaFramework\Support\Traits\Responses;
use Throwable;

class Cropper
{
    use Responses;

    private ImageManager $imageManager;

    public function __construct()
    {
        // Initialize ImageManager with GD driver (you can switch to ImagickDriver if needed)
        $this->imageManager = new ImageManager(new GdDriver());
    }

    public static function crop(): array
    {
        $cropper = new Cropper;
        $cropper->enableAjaxMode();

        try {
            $media = Media::query()->findOrFail(request('object_id'));

            // For ghost models, inject the model instance
            if ($media->model_id === null) {
                // Create a new instance of the model class
                $modelClass = $media->model_type;
                if (class_exists($modelClass)) {
                    $model = new $modelClass();
                    $media->setRelation('model', $model);
                }
            }

            $file = $media->file('xl');
            $cropKey = request('crop_key', 'default');

            $image = $cropper->imageManager->read($file);

            // Create filename with cropped_ prefix and crop key
            $filename = Path::mediaFolderForMedia($media) . '/cropped_' . $cropKey . '_' . $media->filename . '.' . $media->extension();

            // Chain crop and resize operations, then encode
            $processedImage = $image
                ->crop(
                    (int)request('wimage'),
                    (int)request('himage'),
                    (int)request('x1image'),
                    (int)request('y1image')
                )
                ->scaleDown(
                    request('wiimage'),
                    request('heimage')
                );

            $encodedImage = str_contains($media->mime, 'png')
                ? $processedImage->toPng()
                : $processedImage->toJpeg(80);

            Config::getDisk()->put($filename, $encodedImage);

            $cropable = new Cropable($media);
            $cropable->setCurrentCropKey($cropKey);
            $cropable->setWidth((int)request('wiimage'));
            $cropable->setHeight((int)request('heimage'));

            $img = Config::getDisk()->url($filename);
            $cropper->responseElement('sizes', $cropable->printSizes());
            $cropper->responseElement('cropable_links', $cropable->links());
            $cropper->responseElement('uploaded', $media);
            $cropper->responseElement('callback', 'mediaclassCropped');
            $cropper->responseElement('crop_key', $cropKey);
            $cropper->responseElement('urls', ['xl' => $img, 'sm' => $img]);
            $cropper->responseSuccess('Image recadrée avec succès');

        } catch (Throwable $e) {
            $cropper->responseException($e);

        } finally {
            return $cropper->fetchResponse();
        }
    }

    public static function deleteCrop(): array
    {
        $cropper = new Cropper;
        $cropper->enableAjaxMode();

        try {
            $mediaId = request('media_id');
            $cropKey = request('crop_key');

            $media = Media::query()->findOrFail($mediaId);

            // For ghost models, inject the model instance
            if ($media->model_id === null) {
                // Create a new instance of the model class
                $modelClass = $media->model_type;
                if (class_exists($modelClass)) {
                    $model = new $modelClass();
                    $media->setRelation('model', $model);
                }
            }

            // Build the crop filename with cropped_ prefix and key
            $filename = 'cropped_' . $cropKey . '_' . $media->filename . '.' . $media->extension();
            $path = Path::mediaFolderForMedia($media) . '/' . $filename;

            // Delete the cropped file
            if (Config::getDisk()->exists($path)) {
                Config::getDisk()->delete($path);
            }

            // Get updated cropable instance for regenerating links
            $cropable = new Cropable($media);

            $cropper->responseElement('success', true);
            $cropper->responseElement('crop_key', $cropKey);
            $cropper->responseElement('media_id', $mediaId);
            $cropper->responseElement('cropable_links', $cropable->links());
            $cropper->responseElement('callback', 'mediaclassDeletedCrop');
            $cropper->responseSuccess(__('Recadrage supprimé avec succès'));

        } catch (Throwable $e) {
            $cropper->responseException($e);
        }

        return $cropper->fetchResponse();
    }
}
