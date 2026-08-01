# MetaFramework Mediaclass

[![Tests](https://github.com/aboleon/metaframework-mediaclass/actions/workflows/tests.yml/badge.svg)](https://github.com/aboleon/metaframework-mediaclass/actions)
[![codecov](https://codecov.io/gh/aboleon/metaframework-mediaclass/graph/badge.svg)](https://codecov.io/gh/aboleon/metaframework-mediaclass)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/aboleon/metaframework-mediaclass.svg?style=flat-square)](https://packagist.org/packages/aboleon/metaframework-mediaclass)
[![Total Downloads](https://img.shields.io/packagist/dt/aboleon/metaframework-mediaclass.svg?style=flat-square)](https://packagist.org/packages/aboleon/metaframework-mediaclass)
[![PHP Version](https://img.shields.io/packagist/php-v/aboleon/metaframework-mediaclass.svg?style=flat-square)](https://packagist.org/packages/aboleon/metaframework-mediaclass)
[![License](https://img.shields.io/packagist/l/aboleon/metaframework-mediaclass.svg?style=flat-square)](https://packagist.org/packages/aboleon/metaframework-mediaclass)

Media management components for Laravel applications. This package provides
upload UI, database persistence, image resizing, optional cropping, and helpers
to retrieve and render media for Eloquent models.

## Quick Start

```php
// Get image URL
$url = $post->img('cover')->url();

// Get img tag
{!! $post->img('cover')->class('rounded')->lazy()->img() !!}

// In Blade
<x-mfw-media :src="$post->img('cover')" class="rounded" lazy />
```

## Installation

```bash
composer require aboleon/metaframework-mediaclass
```

Publish package resources after install or update:

```bash
php artisan mediaclass:update --force
```

The update command replaces the package-owned
`public/vendor/mfw-mediaclass` directory before publishing. This removes assets
deleted by newer package versions instead of leaving stale files from older
releases. Do not customize files inside that published asset directory.

On first install, publish the config without `--force`, then set its `disk` to
the application filesystem disk that owns the media paths:

```bash
php artisan mediaclass:update --config
```

Normal package updates never publish the config, including when `--force` is
used. This preserves application-owned disk, dimensions, subgroup, and path
settings. Use `--config --force` only when intentionally replacing the
application config with package defaults.

When a release adds migrations, publish and run them explicitly:

```bash
php artisan mediaclass:update --force --migrate
```

Use `--views` only when the application needs to customize the package Blade
views. Views are not published by default so package updates can keep improving
the upstream UI.

The stored-media component loads LightGallery `2.8.3` from
`https://cdnjs.cloudflare.com`, including its bundled CSS and the core, zoom,
thumbnail, and video scripts. The package does not publish a local LightGallery
distribution. Applications with a Content Security Policy must allow this host
in `script-src` and `style-src`.

## Version Tracks

Mediaclass `0.x` is the jQuery uploader line. Applications that want the
existing jQuery UI and do not want the v1 Svelte UI should require the `0.x`
track explicitly:

```bash
composer require aboleon/metaframework-mediaclass:"0.*"
```

For the current stable jQuery release line only:

```bash
composer require aboleon/metaframework-mediaclass:"^0.16"
```

Mediaclass `1.x` is the Svelte UI line. The Svelte assets are built and shipped
inside the Composer package, so consuming Laravel applications should not need
Node, npm, or a Svelte build step just to use the package. After updating the
Composer dependency, run:

```bash
php artisan mediaclass:update --force
```

The `1.x` package does not ship or load Blueimp jQuery File Upload. Its uploader,
video URL form, queue, progress state, and validation UI are Svelte. jQuery is
still required by the current media-management bridge for stored-media sorting,
deletion, descriptions, cropping, subgroups, and LightGallery integration.

During v1 development, applications can test the branch with Composer's dev
constraint:

```bash
composer require aboleon/metaframework-mediaclass:"1.x-dev"
```

### Frontend Asset Development

The v1 frontend source lives in `resources/svelte`. `Uploadable.svelte` owns the
upload UI and `media-manager.js` owns stored-media interactions. Vite compiles
both into the single shipped
`public/vendor/mfw-mediaclass/mediaclass-uploader.js` bundle. Package
maintainers must run the frontend checks and rebuild the shipped bundle before
tagging a release:

```bash
npm install
npm run check
npm run build
```

Uploader styles live in
`public/vendor/mfw-mediaclass/css/styles.css` and are processed by the existing
CSSCrush integration. Svelte components must not contain component-level style
blocks or inject CSS into the compiled JavaScript bundle.

The Vite build disables `publicDir` intentionally because the bundle output is
inside the package `public` tree. Do not re-enable public copying for this build.

### Blade Components

The active v1 Blade surface is:

- `<x-mediaclass::uploadable>` for the Svelte mount point and media context.
- `<x-mediaclass::stored>` for server-rendered existing media.
- `<x-mediaclass::printer>` and `<x-mfw-media>` for frontend rendering.
- Internal crop and confirmation modal components used by `uploadable`.

The old Blueimp upload-template component was removed in `1.x`; it is not a
supported Blade API.

The small published `jcrop` directory remains because the active crop editor
still loads Jcrop. It is independent from the removed Blueimp uploader.

Applications installing the package through Composer do not run these npm
commands. They receive the precompiled bundle and only need:

```bash
composer update aboleon/metaframework-mediaclass
php artisan mediaclass:update --force
```

## Laravel Compatibility

The package supports Laravel majors through explicit Illuminate constraints in
`composer.json`, currently `^11.0|^12.0|^13.0`. A future Laravel major does not
automatically require a Mediaclass major release. The package should widen its
Laravel constraints and tag a minor or patch release when the public API and
published assets remain compatible.

Use a new Mediaclass major only when the package drops an older Laravel major,
changes the public PHP or Blade contract, changes installation behavior in a
breaking way, or replaces an implementation detail that applications can
reasonably depend on.

The current v1 direction keeps Mediaclass Laravel-bound with a shipped Svelte
bundle. If Mediaclass later needs to live as a framework-neutral uploader, the
better split is a Lit + TypeScript web-component package with a Laravel bridge
package around routes, persistence, configuration, and Blade helpers. That split
is larger than the v1 Svelte migration and should be treated as a separate
product boundary.

## Model Setup

```php
use Illuminate\Database\Eloquent\Model;
use MetaFramework\Mediaclass\Contracts\MediaclassInterface;
use MetaFramework\Mediaclass\Concerns\Mediaclass as MediaclassTrait;

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
            ],
        ];
    }
}
```

---
## Two Groups Example (Cover + Gallery)

Use group keys in `mediaclassSettings()` to define the required dimensions per group:

```php
public function mediaclassSettings(): array
{
    return [
        'cover' => [
            'label' => 'Cover',
            'width' => 1600,
            'height' => 900,
            'cropable' => true, // single crop using the group dimensions
        ],
        'gallery' => [
            'label' => 'Gallery',
            'width' => 1200,
            'height' => 800,
            // 'cropable' => ['thumb' => [400, 300]] // optional extra crops
        ],
    ];
}
```

If no group is defined, the package falls back to the default sizes defined in
`config/mfw-mediaclass.php` under `dimensions`.

---
## Group-Specific Sizes

You can define multiple sizes for a single group using a `sizes` array. These
sizes will be used for resizing and for size keys when calling `url('key')`:

```php
public function mediaclassSettings(): array
{
    return [
        'cover' => [
            'label' => 'Cover',
            'sizes' => [
                'xl' => ['width' => 1600, 'height' => 900],
                'sm' => ['width' => 1200, 'height' => 500],
            ],
            'cropable' => true, // uses the largest size as the crop target
        ],
    ];
}
```

If `sizes` is not provided for a group, the package uses the single `width` /
`height` pair for that group, or falls back to the global `dimensions` defaults.

**Note:** Upload processing relies on Intervention Image. If Intervention Image
is not installed, upload tests that hit the upload controller will be skipped.

---

## Displaying Images

### Fluent API (Recommended)

The simplest way to display images from your models:

```php
// Get URL
$url = $post->img('cover')->url();
$url = $post->img('cover')->url('lg');    // specific size

// Get img tag
$html = $post->img('cover')->img();
$html = $post->img('cover')
    ->class('rounded-lg shadow')
    ->alt('Product photo')
    ->lazy()
    ->img();

// Get cropped version
$url = $post->img('cover')->crop('banner')->url();

// Check if media exists
if ($post->img('cover')->exists()) {
    // ...
}

// Multiple images
foreach ($post->imgs('gallery') as $img) {
    echo $img->url();
}
```

### Available Methods

| Method | Description |
|--------|-------------|
| `->url(?string $size)` | Get URL (default: 'sm') |
| `->img(?string $size)` | Get `<img>` tag |
| `->picture(?array $breakpoints)` | Get `<picture>` element |
| `->background()` | Get CSS background-image style |
| `->urls()` | Get all available URLs as array |

**Size Methods:**
```php
->size('lg')      // Set size
->sm() / ->md() / ->lg() / ->xl()  // Shorthand
```

**Crop Methods:**
```php
->crop('banner')  // Use specific crop
->hasCrop('banner')  // Check if crop exists
```

**HTML Attributes:**
```php
->class('rounded')   // CSS classes
->addClass('shadow') // Add to existing classes
->alt('Description') // Alt text
->id('hero-image')   // ID attribute
->lazy()             // loading="lazy"
->eager()            // loading="eager"
->width(800)         // Width attribute
->height(600)        // Height attribute
->attr('data-id', 1) // Any attribute
->attrs(['class' => 'rounded', 'id' => 'img'])
->data('gallery', 'main')  // data-* attributes
```

**Fallback:**
```php
->default('/img/fallback.png')  // Custom fallback URL
->noDefault()                    // No fallback image
```

### Blade Component

```blade
{{-- Basic usage --}}
<x-mfw-media :src="$post->img('cover')" />

{{-- With attributes --}}
<x-mfw-media
    :src="$post->img('cover')"
    size="lg"
    class="rounded-lg"
    alt="Product image"
    lazy
/>

{{-- From model directly --}}
<x-mfw-media :model="$post" group="cover" size="lg" />

{{-- As URL only --}}
<x-mfw-media :src="$post->img('cover')" type="url" />

{{-- As picture element --}}
<x-mfw-media :src="$post->img('cover')" type="picture" />

{{-- With specific crop --}}
<x-mfw-media :src="$post->img('cover')" crop="banner" />
```

**Component Attributes:**

| Attribute | Type | Description |
|-----------|------|-------------|
| `src` | MediaBuilder | From `$model->img('group')` |
| `model` | object | Model instance (with `group`) |
| `group` | string | Media group name |
| `subgroup` | string | Media subgroup filter |
| `size` | string | Image size (sm, md, lg, xl) |
| `type` | string | Output: img, url, picture, background |
| `class` | string | CSS classes |
| `alt` | string | Alt text |
| `id` | string | HTML id |
| `lazy` | bool | Enable lazy loading |
| `crop` | string | Specific crop key |
| `default` | string | Fallback URL |
| `noDefault` | bool | Disable fallback |
| `data` | array | Data attributes |
| `breakpoints` | array | For picture element |

### Direct Media Model Usage

```php
// If you have a Media model directly
$media = Media::find(1);

$url = $media->url('lg');
$url = $media->crop('banner');
$html = $media->img('md', ['class' => 'rounded']);

// Fluent builder
$html = $media->builder()
    ->class('rounded')
    ->lazy()
    ->img();
```

---

## Uploading Media

### Upload Component

```blade
<x-mediaclass::uploadable
    :model="$post"
    group="cover"
    :limit="1"
/>
```

With options:

```blade
<x-mediaclass::uploadable
    :model="$post"
    group="gallery"
    :limit="10"
    :positions="true"
    :description="true"
    maxfilesize="5MB"
    :cropable="['thumb' => [400, 300]]"
/>
```

### Stored Media Display (Admin)

```blade
<x-mediaclass::stored :model="$post" group="gallery" />
```

### Dynamic Subgroups in the Upload UI

Mediaclass stores an optional `subgroup` on each media row. You can enable an
admin-side subgroup selector for an uploadable group so editors can assign each
uploaded image to a preset subgroup without creating separate upload slots.

Configure presets globally:

```php
// config/mfw-mediaclass.php
'subgroups' => [
    'count' => 5,
    'label' => 'Group',
    'empty_label' => 'Normal flow',
    'key_prefix' => 'group_',
    'groups' => [
        'gallery' => true,
    ],
],
```

Or define explicit labels:

```php
'subgroups' => [
    'groups' => [
        'gallery' => [
            'options' => [
                'featured' => 'Featured',
                'flow' => 'Flow',
            ],
        ],
    ],
],
```

You can also define subgroup presets on a model group:

```php
public function mediaclassSettings(): array
{
    return [
        'gallery' => [
            'label' => 'Gallery',
            'width' => 1200,
            'height' => 800,
            'subgroups' => [
                'count' => 5,
                'label' => 'Group',
            ],
        ],
    ];
}
```

When subgroups are enabled, `<x-mediaclass::uploadable>` injects a select into
each native uploaded image row. The select saves through the package AJAX route:

```text
POST /mediaclass-ajax
action=saveSubgroup
```

The response triggers a jQuery document event:

```js
$(document).on('mediaclass:subgroup-saved', function (event, result, uploadable, select) {
    // result.group, result.media_id, result.subgroup, result.uses_subgroups
});
```

Frontend rendering remains application-owned. A common pattern is to render
media with `subgroup = null` in normal flow, and render media sharing the same
subgroup as a grid.

### Processing After Save

```php
$post = Post::create($payload);
$post->processMedia();
```

---

## Configuration

Published to `config/mfw-mediaclass.php`:

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

---

## Cropping

Define cropable settings in your model:

```php
public function mediaclassSettings(): array
{
    return [
        'cover' => [
            'width' => 1600,
            'height' => 900,
            'cropable' => true,  // Single crop using group dimensions
        ],
        'banner' => [
            'width' => 1920,
            'height' => 400,
            'cropable' => [
                'desktop' => [1920, 400],
                'mobile' => [800, 400],
            ],
        ],
    ];
}
```

Access cropped versions:

```php
// Single crop
$url = $post->img('cover')->crop('cover')->url();

// Multiple crops
$desktop = $post->img('banner')->crop('desktop')->url();
$mobile = $post->img('banner')->crop('mobile')->url();

// Check if crop exists
if ($post->img('cover')->hasCrop('cover')) {
    // ...
}
```

---

## Ghost Media

For media not attached to a specific model instance:

```blade
<x-mediaclass::uploadable :model="$post" group="cover" ghost />
```

Retrieve ghost media:

```php
use MetaFramework\Mediaclass\Mediaclass;

$url = Mediaclass::ghostUrl(Post::class, 'cover', 'sm', '/fallback.png');
```

---

## External Video Embeds

External video media and supported oEmbed URLs can be rendered through the
Mediaclass facade or helper:

```php
use MetaFramework\Mediaclass\Facades\MediaclassFacade;

$html = MediaclassFacade::embed($media, ['loading' => 'lazy']);
$html = mediaclass_embed('https://www.youtube.com/watch?v=...');
```

Embeds default to `560 × 315`. External video media store their display
dimensions in the media `storable` data. The uploader UI supports a pixel width
or a responsive `100%` width:

```php
$media->storable = [
    'url' => 'https://www.youtube.com/watch?v=...',
    'embed_width' => '100%',
    'embed_height' => 315,
];
```

Explicit helper options override the stored dimensions.

The package also supports registered embed providers for player URLs that do
not expose oEmbed metadata. TF1 Info player URLs are supported by default:

```php
$html = mediaclass_embed(
    'https://www.tf1info.fr/player/647975e0-402c-4608-84ad-c12a3bb63ede/',
    ['width' => '100%', 'loading' => 'lazy'],
);
```

### Custom Embed Providers

Implement `EmbedProvider` to support another external player. Providers return
structured embed data instead of HTML; Mediaclass keeps URL validation,
attribute allowlisting, escaping, and iframe rendering centralized:

```php
use MetaFramework\Mediaclass\Contracts\EmbedProvider;
use MetaFramework\Mediaclass\Data\ExternalVideoEmbed;

final class ExampleEmbedProvider implements EmbedProvider
{
    public function supports(string $url): bool
    {
        return str_starts_with($url, 'https://video.example.com/player/');
    }

    public function embed(string $url): ?ExternalVideoEmbed
    {
        return $this->supports($url)
            ? new ExternalVideoEmbed($url, ['allowfullscreen' => true])
            : null;
    }
}
```

Register the provider from an application or package service provider:

```php
use MetaFramework\Mediaclass\Support\EmbedProviderManager;

$this->callAfterResolving(
    EmbedProviderManager::class,
    static function (EmbedProviderManager $manager): void {
        $manager->register(ExampleEmbedProvider::class);
    },
);
```

Mediaclass tries standard oEmbed first, registered providers in registration
order second, and the direct HTML5 video fallback last. Provider failures are
isolated so another provider or fallback can still render the media.

The back-office uploader stores the provider thumbnail when available. Existing
external videos resolve and cache their oEmbed thumbnail on first display. Video
previews open in the CDN-hosted LightGallery viewer and autoplay through its
video plugin.

Unsupported URLs and provider failures return an empty `HtmlString`.

---

## Legacy API

The original Parser/Printer classes are still available for backward compatibility:

```php
use MetaFramework\Mediaclass\Mediaclass;
use MetaFramework\Mediaclass\Printer;

// Fetch and parse
$parser = (new Mediaclass())->forModel($post, 'cover')->first();
$url = $parser->url;

// Render with Printer
$html = (new Printer($parser))
    ->setClass('rounded')
    ->setLoading('lazy')
    ->img('md');
```

---

## Storage Layout

- Regular: `{model}/{id}/{width}_{filename}.{ext}`
- Ghost: `{model}/{width}_{filename}.{ext}`
- Crops: `{model}/{id}/cropped_{key}_{filename}.{ext}`

## Routes

- `POST /mediaclass-ajax` - Upload/delete/crop actions
- `GET /mediaclass/cropable/{media}` - Crop UI

## Testing

```bash
composer install
composer test
```

## Requirements

- PHP 8.3+
- Laravel 11+
- Intervention Image
