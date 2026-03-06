<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'api_key',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'api_key',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    /** Auto-generate api_key on creation. */
    protected static function booted(): void
    {
        static::creating(function (User $user) {
            if (empty($user->api_key)) {
                $user->api_key = Str::random(64);
            }
        });
    }

    /** Generate a new random API key and persist it. */
    public function rotateApiKey(): string
    {
        $this->api_key = Str::random(64);
        $this->saveQuietly();
        return $this->api_key;
    }

    /** Masked key for display: show first 8 + last 4 chars. */
    public function getMaskedApiKeyAttribute(): string
    {
        if (!$this->api_key) {
            return '';
        }
        return substr($this->api_key, 0, 8) . str_repeat('•', 52) . substr($this->api_key, -4);
    }
}
