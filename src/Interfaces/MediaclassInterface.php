<?php

namespace MetaFramework\Mediaclass\Interfaces;

use Illuminate\Database\Eloquent\Relations\MorphMany;

interface MediaclassInterface
{
    public function media(): MorphMany;
    public function model(): object;
}
