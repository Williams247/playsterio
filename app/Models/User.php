<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Fluent;
use Laravel\Sanctum\HasApiTokens;
use MongoDB\Laravel\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasFactory, HasApiTokens, Notifiable;

    protected $connection = 'mongodb';

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $casts = [
        'password' => 'hashed',
    ];

    /**
     * Sanctum's default token model extends SQL Eloquent; ours is MongoDB. Build the token here so inserts use the MongoDB driver.
     *
     * @return \Illuminate\Support\Fluent
     */
    public function createToken(string $name, array $abilities = ['*'], ?DateTimeInterface $expiresAt = null)
    {
        $plainTextToken = $this->generateTokenString();

        $token = $this->tokens()->create([
            'name' => $name,
            'token' => hash('sha256', $plainTextToken),
            'abilities' => $abilities,
            'expires_at' => $expiresAt,
        ]);

        return new Fluent([
            'accessToken' => $token,
            'plainTextToken' => $token->getKey().'|'.$plainTextToken,
        ]);
    }
}

?>
