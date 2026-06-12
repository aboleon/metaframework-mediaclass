<?php

declare(strict_types=1);

namespace MetaFramework\Mediaclass\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $model_type
 * @property int $model_id
 * @property string $access_key
 */
class ModelKey extends Model
{
    protected $table = 'mediaclass_model_keys';

    protected $guarded = [];
}
