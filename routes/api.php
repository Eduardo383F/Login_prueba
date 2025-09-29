<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ReservationController;
use App\Support\ApiResponse;
use App\Http\Controllers\AvailabilityController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\ReservationActionsController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ReservationDocsController;
use App\Http\Controllers\RoomsPublicController;
use App\Http\Controllers\ContentPublicController;
use App\Http\Controllers\TestimonialsController;

/*
|--------------------------------------------------------------------------
| Rutas públicas (clientes)
|--------------------------------------------------------------------------
*/

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',    [AuthController::class, 'login']);
Route::get('/ping', fn() => response()->json(['message' => 'API funcionando']));
Route::get('/availability', [AvailabilityController::class, 'index']);
Route::middleware('auth:sanctum')->post('/checkout', [CheckoutController::class, 'store']);
Route::middleware('auth:sanctum')->post('/checkin/scan', [ReservationActionsController::class, 'scanQr']);
Route::middleware('auth:sanctum')->get('/reservations', [ReservationController::class, 'index']);
Route::middleware(['auth:sanctum','role:cliente'])->group(function () {
    Route::post('/reservations', [ReservationController::class, 'store']);
});
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/testimonials', [TestimonialsController::class, 'store']);
});
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/reservations/{id}/checkout', [ReservationActionsController::class, 'checkout']);
});

// Público – catálogo web
Route::get('/rooms', [\App\Http\Controllers\RoomsPublicController::class, 'index']);

Route::get('/about', [\App\Http\Controllers\ContentPublicController::class, 'about']);

Route::get('/offers', [\App\Http\Controllers\ContentPublicController::class, 'offers']);
Route::get('/offers/{id}', [\App\Http\Controllers\ContentPublicController::class, 'offerShow']);

Route::get('/news', [\App\Http\Controllers\ContentPublicController::class, 'news']);
Route::get('/news/{id}', [\App\Http\Controllers\ContentPublicController::class, 'newsShow']);

Route::get('/testimonials', [\App\Http\Controllers\ContentPublicController::class, 'testimonials']);

Route::get('/events', [\App\Http\Controllers\ContentPublicController::class, 'events']);
Route::get('/events/{id}', [\App\Http\Controllers\ContentPublicController::class, 'eventShow']);


/*
|--------------------------------------------------------------------------
| Rutas protegidas (clientes con Sanctum)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    // Zona solo para clientes (validando el ENUM role de users)
    Route::get('/zona-cliente', function (Request $request) {
        if ($request->user()->role !== 'cliente') {
            return ApiResponse::error('Acceso prohibido', [], 403);
        }

        return ApiResponse::success('Zona solo para clientes', [
            'id'   => $request->user()->id,
            'name' => $request->user()->name,
        ], 200);
    });
});


Route::middleware('auth:sanctum')->group(function () {
    Route::post('/checkin/scan', [ReservationActionsController::class, 'scanQr']);
    Route::post('/reservations/{id}/send-confirmation', [ReservationActionsController::class, 'sendConfirmation']);
});

Route::middleware('auth:sanctum')->group(function () {

    // Reserva (pre‑reserva y detalle)
    Route::post('/reservations', [ReservationController::class, 'store']);
    Route::get('/reservations/{id}', [ReservationController::class, 'show']);

    // Pago
    Route::post('/reservations/{id}/pay', [PaymentController::class, 'payReservation']);

    // Confirmación + QR (ya lo tienes)
    Route::post('/reservations/{id}/send-confirmation', [ReservationActionsController::class, 'sendConfirmation']);

    // Check‑in por QR (ya lo tienes)
    Route::post('/checkin/scan', [ReservationActionsController::class, 'scanQr']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/reservations/{id}/voucher.pdf',   [ReservationDocsController::class, 'voucher']);
    Route::get('/reservations/{id}/calendar.ics',  [ReservationDocsController::class, 'calendar']);
});

