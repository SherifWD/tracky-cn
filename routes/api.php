<?php

use App\Http\Api\Controllers\SubscriptionController;
use App\Http\Controllers\Api\UserFlowController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MetaController;
use App\Http\Middleware\JwtMiddleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


// Authentication Routes
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
});

Route::prefix('auth')->middleware([JwtMiddleware::class])->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::post('update-profile', [AuthController::class, 'updateProfile']);
    Route::get('auto-login', [AuthController::class, 'getAuthenticatedUser']);
});

Route::group(['middleware' => 'jwt.verify'], function() {
    Route::get('meta-data', [MetaController::class, 'getMetaData']);
    Route::post('reserve-translator', [UserFlowController::class, 'reserveTranslator']);
    Route::post('calculate-reservation', [UserFlowController::class, 'calculateReservationPrice']);
    Route::post('calculate-cbm', [UserFlowController::class, 'calculateCBM']);
    Route::post('receipt-payment', [UserFlowController::class, 'ReceiptPayment']);
    Route::post('calculate-price-container', [UserFlowController::class, 'getPriceContainerByHarbor']);
    Route::post('reserve-container', [UserFlowController::class, 'reserveShipping']);
    Route::post('/track-shipment', [SubscriptionController::class, 'subscribeToShipmentTracking']);


});

?>