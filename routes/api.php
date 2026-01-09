<?php

use App\Http\Controllers\Auth\MobileOtpController;
use App\Http\Controllers\Auth\MobileRegistrationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::prefix('auth')->group(function () {
    Route::post('/request-otp', [MobileOtpController::class, 'requestOtp']);
    Route::post('/verify-otp', [MobileOtpController::class, 'verifyOtp']);
    Route::post('/register/request-otp', [MobileRegistrationController::class, 'requestOtp']);
    Route::post('/register/verify-otp', [MobileRegistrationController::class, 'verifyOtp']);
    Route::middleware('auth:sanctum')->post('/logout', [MobileOtpController::class, 'logout']);
});
