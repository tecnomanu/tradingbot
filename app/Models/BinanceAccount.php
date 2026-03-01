<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class BinanceAccount extends Model
{
    protected $fillable = [
        'user_id',
        'label',
        'api_key',
        'api_secret',
        'is_testnet',
        'is_active',
        'last_connected_at',
    ];

    protected $casts = [
        'is_testnet' => 'boolean',
        'is_active' => 'boolean',
        'last_connected_at' => 'datetime',
    ];

    protected $hidden = ['api_key', 'api_secret'];

    // Encrypt API key on set
    public function setApiKeyAttribute(string $value): void
    {
        $this->attributes['api_key'] = Crypt::encryptString($value);
    }

    // Decrypt API key on get
    public function getApiKeyAttribute(?string $value): ?string
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function setApiSecretAttribute(string $value): void
    {
        $this->attributes['api_secret'] = Crypt::encryptString($value);
    }

    public function getApiSecretAttribute(?string $value): ?string
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bots(): HasMany
    {
        return $this->hasMany(Bot::class);
    }

    /**
     * Get a masked version of the API key for display purposes.
     */
    public function getMaskedApiKeyAttribute(): string
    {
        $key = $this->api_key;
        if (!$key) {
            return '';
        }
        return substr($key, 0, 6) . '****' . substr($key, -4);
    }
}
