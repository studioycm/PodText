<?php

use App\Models\ContentTag;
use Illuminate\Database\Eloquent\Relations\MorphPivot;

return [
    'slugger' => null,

    'tag_model' => ContentTag::class,

    'taggable' => [
        'table_name' => 'taggables',
        'morph_name' => 'taggable',
        'class_name' => MorphPivot::class,
    ],
];
