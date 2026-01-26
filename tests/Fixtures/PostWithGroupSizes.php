<?php

namespace MetaFramework\Mediaclass\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use MetaFramework\Mediaclass\Contracts\MediaclassInterface;
use MetaFramework\Mediaclass\Concerns\Mediaclass;

class PostWithGroupSizes extends Model implements MediaclassInterface
{
    use Mediaclass;

    protected $guarded = [];
    protected $table = 'posts';

    public function mediaclassSettings(): array
    {
        return [
            'cover' => [
                'label' => 'Cover',
                'sizes' => [
                    'xl' => ['width' => 1600, 'height' => 900],
                    'sm' => ['width' => 1200, 'height' => 500],
                ],
                'cropable' => true,
            ],
        ];
    }
}
