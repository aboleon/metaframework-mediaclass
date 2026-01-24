<?php

namespace MetaFramework\Mediaclass\Contracts;

use Illuminate\Database\Eloquent\Relations\MorphMany;

interface MediaclassInterface
{
    public function media(): MorphMany;
    public function model(): object;
}
