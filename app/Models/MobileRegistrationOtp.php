<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MobileRegistrationOtp extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'phone',
        'code',
        'expires_at',
        'attempts',
        'used_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    public function hasExpired(): bool
    {
        return $this->expires_at?->isPast() ?? true;
    }
}
