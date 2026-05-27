<?php

declare(strict_types=1);

namespace MetaFramework\Mediaclass\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use MetaFramework\Mediaclass\Contracts\MediaclassInterface;
use MetaFramework\Mediaclass\Models\Media;

class PostWithoutInstanceModelMethod extends Model implements MediaclassInterface
{
    protected $guarded = [];

    protected $table = 'posts';

    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'model');
    }

    public function model(): static
    {
        return $this;
    }

    public function mediaclassSettings(): array
    {
        return [
            'cover' => [
                'label' => 'Cover',
                'width' => 1600,
                'height' => 900,
                'cropable' => true,
            ],
        ];
    }
}
