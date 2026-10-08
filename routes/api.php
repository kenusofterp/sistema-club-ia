<?php

use App\Http\Controllers\Api\AccessController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MemberController;
use App\Http\Controllers\Api\PublicController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 (autenticación con tokens de Laravel Sanctum: "Authorization: Bearer <token>")
|--------------------------------------------------------------------------
*/
Route::prefix('v1')->name('api.')->middleware('throttle:api')->group(function () {
    // Público
    Route::get('/site', [PublicController::class, 'site'])->name('site');
    Route::get('/activities', [PublicController::class, 'activities'])->name('activities');
    Route::get('/plans', [PublicController::class, 'plans'])->name('plans');
    Route::post('/auth/token', [AuthController::class, 'token'])->name('auth.token');

    Route::middleware(['auth:sanctum', 'active'])->group(function () {
        Route::delete('/auth/token', [AuthController::class, 'revoke'])->name('auth.revoke');

        // Socio autenticado
        Route::get('/me', [MemberController::class, 'me'])->name('me');
        Route::get('/me/fees', [MemberController::class, 'fees'])->name('me.fees');
        Route::get('/me/payments', [MemberController::class, 'payments'])->name('me.payments');
        Route::get('/me/subscriptions', [MemberController::class, 'subscriptions'])->name('me.subscriptions');
        Route::get('/me/enrollments', [MemberController::class, 'enrollments'])->name('me.enrollments');
        Route::post('/me/enrollments', [MemberController::class, 'enroll'])->name('me.enroll');
        Route::delete('/me/enrollments/{activity:id}', [MemberController::class, 'unenroll'])->name('me.unenroll');
        Route::get('/me/reservations', [MemberController::class, 'reservations'])->name('me.reservations');
        Route::post('/me/reservations', [MemberController::class, 'book'])->name('me.book');
        Route::delete('/me/reservations/{reservation}', [MemberController::class, 'cancelReservation'])->name('me.cancel-reservation');
        Route::get('/facilities/{facility}/availability', [MemberController::class, 'availability'])->name('facilities.availability');

        // Personal: control de acceso
        Route::post('/access/check', [AccessController::class, 'check'])->name('access.check');
    });
});
