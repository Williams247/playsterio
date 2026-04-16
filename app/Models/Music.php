<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Music extends Model
{
    protected $connection = "mongodb";
    protected $table = "music";

    protected $fillable = [
        "title",
        "filename",
        "music_url",
        "thumbnail_url",
        "duration",
        "description",
        "category",
        "disabled"
    ];

    protected $casts = [
        'disabled' => 'boolean',
    ];
}

?>
