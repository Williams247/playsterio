<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Otp extends Model
{
    protected $connection = 'mongodb';

    protected $table = 'otp';

    protected $fillable = [
        'email',
        'otp_code',
        'otp_type',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];
}

?>
