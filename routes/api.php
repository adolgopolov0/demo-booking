<?php

use App\Http\Controllers\BookingController;
use Illuminate\Support\Facades\Route;

Route::get('/documentation', function () {
    return view('swagger.booking');
});

Route::get('/documentation/openapi.yaml', function () {
    return response()->file(app_path('Http/Controllers/BookingController.yaml'), [
        'Content-Type' => 'application/yaml',
    ]);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/bookings', [BookingController::class, 'index']);
    Route::post('/bookings', [BookingController::class, 'store']);
    Route::delete('/bookings/{booking}', [BookingController::class, 'destroy']);
    Route::get('/rooms/{room}/bookings/today', [BookingController::class, 'today']);
});
