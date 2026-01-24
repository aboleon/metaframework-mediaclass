<?php

namespace MetaFramework\Mediaclass\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use MetaFramework\Mediaclass\Contracts\MediaclassInterface;
use MetaFramework\Mediaclass\Concerns\Mediaclass;

class Post extends Model implements MediaclassInterface
{
    use Mediaclass;

    protected $guarded = [];

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
