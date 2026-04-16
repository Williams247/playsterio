<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Category extends Model
{
    protected $connection = "mongodb";
    protected $table = "category";

    protected $fillable = ["title"];
}

?>
