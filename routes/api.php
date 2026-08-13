<?php

use App\Http\Controllers\Api\MpesaC2BController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| M-Pesa C2B callbacks (Task 3)
|--------------------------------------------------------------------------
|
| These are the two URLs registered with Safaricom Daraja. They are public by
| necessity — Safaricom sends no credentials — so they are protected by the
| IP allow-list middleware and rate limiting instead of auth.
|
| CSRF does not apply: the api group is stateless.
|
*/

Route::prefix('mpesa/c2b')
    ->middleware(['mpesa.callback', 'throttle:mpesa'])
    ->group(function () {
        Route::post('validation', [MpesaC2BController::class, 'validation'])
            ->name('mpesa.c2b.validation');

        Route::post('confirmation', [MpesaC2BController::class, 'confirmation'])
            ->name('mpesa.c2b.confirmation');
    });
