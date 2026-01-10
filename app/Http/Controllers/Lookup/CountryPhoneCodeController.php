<?php

namespace App\Http\Controllers\Lookup;

use App\Http\Controllers\Controller;
use App\Models\CountryPhoneCode;
use Illuminate\Http\JsonResponse;

class CountryPhoneCodeController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $codes = CountryPhoneCode::query()
            ->active()
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'iso_code',
                'dial_code',
                'min_nsn_length',
                'max_nsn_length',
                'example_format',
                'is_default',
            ]);

        return response()->json([
            'data' => $codes,
        ]);
    }
}
