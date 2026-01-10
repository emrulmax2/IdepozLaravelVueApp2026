<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class CountryPhoneCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'iso_code',
        'dial_code',
        'min_nsn_length',
        'max_nsn_length',
        'example_format',
        'is_active',
        'is_default',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'country_phone_code_id');
    }

    public function mobileOtps(): HasMany
    {
        return $this->hasMany(MobileOtp::class, 'country_phone_code_id');
    }

    public function mobileRegistrationOtps(): HasMany
    {
        return $this->hasMany(MobileRegistrationOtp::class, 'country_phone_code_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function normalizeNationalNumber(string $nationalNumber, string $field = 'phone'): string
    {
        $digits = preg_replace('/[^0-9]/', '', $nationalNumber) ?? '';

        if ($digits === '') {
            throw ValidationException::withMessages([
                $field => ['Please enter the rest of your mobile number.'],
            ]);
        }

        $length = strlen($digits);

        if ($length < $this->min_nsn_length || $length > $this->max_nsn_length) {
            throw ValidationException::withMessages([
                $field => [sprintf(
                    'Enter between %d and %d digits for this country.',
                    $this->min_nsn_length,
                    $this->max_nsn_length
                )],
            ]);
        }

        return $this->dial_code . $digits;
    }
}
