<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Middleware\JwtMiddleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


// Authentication Routes
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
});

Route::prefix('auth')->middleware([JwtMiddleware::class])->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::post('update-profile/{id}', [AuthController::class, 'updateProfile']);
    Route::get('auto-login', [AuthController::class, 'getAuthenticatedUser']);
});

?>