# MetaFramework Mediaclass

Media management components for MetaFramework (Laravel). This package provides
upload UI, database persistence, image resizing, optional cropping, and helpers
to retrieve and render media for Eloquent models.

## What this package does

- Adds a polymorphic `mediaclass` table to store images and files for any model.
- Provides Blade components for uploads, listings, and rendering images.
- Generates resized image variants and optional crops per group.
- Supports per-group settings (dimensions, labels, crop variants).
- Handles "ghost" media (records without a model_id) for pre-save workflows.
- Provides Parser and Printer helpers to fetch and render URLs cleanly.

## Requirements

- PHP 8.3+
- Laravel 11 or 12
- Intervention Image (used for resizing/cropping)
- jQuery + Blueimp jQuery File Upload (bundled in assets)
- Bootstrap modals and icon fonts (used by the UI templates)

## Installation

Add the package (path repo already configured in `composer.json`):

```bash
composer require aboleon/metaframework-mediaclass
```

Publish assets, config, language files, and migrations:

```bash
php artisan vendor:publish --tag=mfw-mediaclass-config
php artisan vendor:publish --tag=mfw-mediaclass-views
php artisan vendor:publish --tag=mfw-mediaclass-lang
php artisan vendor:publish --tag=mfw-mediaclass-assets
php artisan vendor:publish --tag=mfw-mediaclass-migrations
```

Run the migration:

```bash
php artisan migrate
```

## Configuration

The config file is published to `config/mfw-mediaclass.php`.

```php
return [
    'disk' => 'public',
    'dimensions' => [
        'xl' => ['width' => 1920, 'height' => 1080],
        'lg' => ['width' => 1400, 'height' => 788],
        'md' => ['width' => 700,  'height' => 394],
        'sm' => ['width' => 400,  'height' => 225],
    ],
];
```

Note: media URLs are built using the `media` disk in `MetaFramework\Mediaclass\Models\Media`.
Make sure `filesystems.disks.media` exists (often same config as `public`), or align
your storage setup accordingly.

## Model setup

Implement the interface and use the trait on your Eloquent model:

```php
use Illuminate\Database\Eloquent\Model;
use MetaFramework\Mediaclass\Interfaces\MediaclassInterface;
use MetaFramework\Mediaclass\Traits\Mediaclass as MediaclassTrait;

class Post extends Model implements MediaclassInterface
{
    use MediaclassTrait;

    public function mediaclassSettings(): array
    {
        return [
            'cover' => [
                'label' => 'Cover',
                'width' => 1600,
                'height' => 900,
                'cropable' => true,
            ],
            'gallery' => [
                'label' => 'Gallery',
                'width' => 1200,
                'height' => 800,
                'cropable' => [
                    'thumb' => [400, 300, 'Thumb'],
                    'banner' => [1600, 400, 'Banner'],
                ],
            ],
        ];
    }
}
```

Call `processMedia()` after create/update to persist descriptions and positions
and to attach temporary uploads:

```php
$post = Post::create($payload);
$post->processMedia();
```

## Blade components

### Uploadable component

```blade
<x-mediaclass::uploadable
    :model="$post"
    group="cover"
    :limit="1"
    :positions="true"
/>
```

With subgroup + custom storables:

```blade
<x-mediaclass::uploadable
    :model="$post"
    group="gallery"
    :settings="['subgroup' => 'front']"
    :storables="['slot' => 'hero']"
/>
```

Make sure your layout includes the stacks used by the component:

```blade
@stack('css')
@stack('js')
```

### Stored component

```blade
<x-mediaclass::stored :model="$post" group="gallery" />
```

### Printer component

```blade
@php
    $cover = Mediaclass::forModel($post, 'cover')->first();
@endphp

<x-mediaclass::printer :model="$cover" size="lg" class="rounded" />
<x-mediaclass::printer :model="$cover" type="picture" />
```

## Backend usage

### Fetch and parse media

```php
use MetaFramework\Mediaclass\Facades\Mediaclass;

$cover = Mediaclass::forModel($post, 'cover')->first();

if ($cover) {
    $url = $cover->url;          // default URL (cropped preferred)
    $thumb = $cover->getUrl('sm');
}
```

### Render with Printer

```php
use MetaFramework\Mediaclass\Printer;

$cover = Mediaclass::forModel($post, 'cover')->first();
$imgTag = $cover ? (new Printer($cover))->setClass('rounded')->img('md') : '';
```

### Helper

```blade
{{ mediaclass_url($cover, 'md') }}
```

### Ghost media URL

```php
use MetaFramework\Mediaclass\Mediaclass;

$url = Mediaclass::ghostUrl(Post::class, 'cover', 'sm', '/img/fallback.png');
```

## Cropping

Cropping is driven by the `cropable` setting:

- `true`: a single crop based on the group dimensions.
- `[w, h]`: a single crop with given dimensions.
- `['key' => [w, h, 'Label']]`: multiple crops, keyed and labeled.

Crop variants are stored as files named:

```
cropped_<key>_<filename>.<ext>
```

The `Parser` and `Printer` classes automatically prefer cropped images when available.

## Ghost uploads vs temp uploads

- **Temp uploads**: when a model has no id yet, the component injects a
  `mediaclass_temp_id` field; `processMedia()` attaches those records after save.
- **Ghost uploads**: set `ghost="true"` on the component to store media with
  `model_id = null`. Use `Mediaclass::ghostUrl()` to retrieve their URL.

Example:

```blade
<x-mediaclass::uploadable :model="$post" group="cover" ghost />
```

## File types and limits

Accepted types: JPEG, PNG, SVG, PDF. Size is controlled by the `maxfilesize`
attribute on the upload component (default is 16MB).

## Storage layout

- Regular models: `snake(model)/{id}/<width>_<filename>.<ext>`
- Ghost models: `snake(model)/<width>_<filename>.<ext>`
- Non-images: `snake(model)/{id}/<filename>.<ext>`

## Routes

The package registers:

- `POST /mediaclass-ajax` (upload/delete/crop actions, `web` middleware)
- `GET /mediaclass/cropable/{media}` (crop UI, `web` + `auth:sanctum`)

## Troubleshooting

- If thumbnails do not resolve, confirm the `media` disk is configured.
- If crops do not show, ensure the crop route is reachable and assets are published.
- If the UI is missing, verify `@stack('css')` and `@stack('js')` are in your layout.
