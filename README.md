# MetaFramework Mediaclass

[![Tests](https://github.com/aboleon/metaframework-mediaclass/actions/workflows/tests.yml/badge.svg)](https://github.com/aboleon/metaframework-mediaclass/actions)
[![codecov](https://codecov.io/gh/aboleon/metaframework-mediaclass/graph/badge.svg)](https://codecov.io/gh/aboleon/metaframework-mediaclass)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/aboleon/metaframework-mediaclass.svg?style=flat-square)](https://packagist.org/packages/aboleon/metaframework-mediaclass)
[![Total Downloads](https://img.shields.io/packagist/dt/aboleon/metaframework-mediaclass.svg?style=flat-square)](https://packagist.org/packages/aboleon/metaframework-mediaclass)
[![PHP Version](https://img.shields.io/packagist/php-v/aboleon/metaframework-mediaclass.svg?style=flat-square)](https://packagist.org/packages/aboleon/metaframework-mediaclass)
[![License](https://img.shields.io/packagist/l/aboleon/metaframework-mediaclass.svg?style=flat-square)](https://packagist.org/packages/aboleon/metaframework-mediaclass)

Media management components for MetaFramework (Laravel). This package provides
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

Publish assets and run migrations:

```bash
php artisan vendor:publish --tag=mfw-mediaclass-config
php artisan vendor:publish --tag=mfw-mediaclass-assets
php artisan vendor:publish --tag=mfw-mediaclass-migrations
php artisan migrate
```

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
