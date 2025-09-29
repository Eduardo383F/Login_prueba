<?php

namespace App\Http\Controllers;

use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\ReservationActionsController;
use Stripe\Stripe;
use Stripe\PaymentIntent;
use Stripe\Exception\CardException;

class PaymentController extends Controller
{
    public function payReservation($id, Request $r)
    {
        // 1. Validación de entrada (lo que enviará React)
        $fields = $r->validate([
            'payment_method_id' => 'required|string',
            'idempotency_key' => 'required|string|max:64',
        ]);

        // 2. Traer reserva y validar propietario y estado (como ya lo tenías)
        $res = DB::table('reservations')->where('id', $id)->first();

        if (!$res) {
            return ApiResponse::error('Recurso no encontrado', [], 404);
        }
        if ($r->user()->id !== (int) $res->user_id) {
            return ApiResponse::error('No autorizado para esta reserva', ['code' => 'FORBIDDEN'], 403);
        }
        if ($res->status !== 'tentativa') {
            return ApiResponse::error('La reserva no está en estado tentativa', ['code' => 'INVALID_STATUS'], 409);
        }

        // 3. Calcular monto final (habitación + extras)
        $roomTotal = (float) $res->total_price;
        $extrasTotal = (float) DB::table('reservation_extra')
            ->where('reservation_id', $res->id)
            ->sum('total_price');
        $amount = $roomTotal + $extrasTotal;

        // --- INICIA LÓGICA DE STRIPE ---
        try {
            Stripe::setApiKey(config('services.stripe.secret'));
           
            // 4. Crear el intento de pago en Stripe
            $paymentIntent = PaymentIntent::create(
                // Parámetros del pago (primer array)
                [
                    'amount' => $amount * 100, // Stripe usa centavos
                    'currency' => 'mxn',
                    'payment_method' => $fields['payment_method_id'],
                    'confirm' => true,
                    'automatic_payment_methods' => [
                        'enabled' => true,
                        'allow_redirects' => 'never',
                    ],
                    'metadata' => [
                        'reservation_id' => $res->id,
                        'user_id' => $r->user()->id,
                    ]
                ],
                // Opción correcta: La llave de idempotencia se pasa aquí, como segundo parámetro
                ['idempotency_key' => $fields['idempotency_key']]
            );

            // --- FIN LÓGICA DE STRIPE ---

            // Si el pago es exitoso, procedemos a guardar todo en la base de datos
            DB::beginTransaction();

            // 5. Insertar el registro del pago
            $paymentId = DB::table('payments')->insertGetId([
                'reservation_id' => $res->id,
                'amount' => $amount,
                'payment_method' => 'tarjeta',
                'payment_date' => now(),
                'status' => 'pagado',
                'reference' => $fields['idempotency_key'],
                'gateway_provider' => 'stripe',
                'gateway_intent_id' => $paymentIntent->id,
                'gateway_charge_id' => $paymentIntent->latest_charge,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 6. Actualizar la reserva a "confirmada"
            $colorId = DB::table('color_codes')->where('color_name', 'azul_cielo')->value('id');
            DB::table('reservations')->where('id', $res->id)->update([
                'status' => 'confirmada',
                'color_code_id' => $colorId ?: null,
                'updated_at' => now(),
            ]);

            DB::commit();

            // 7. Enviar confirmación + QR automáticamente
            $emailSent = false;
            try {
                $emailResponse = app(ReservationActionsController::class)->sendConfirmation($res->id, $r);
                $emailSent = method_exists($emailResponse, 'getStatusCode') ? ($emailResponse->getStatusCode() === 200) : false;
            } catch (\Throwable $e) {
                $emailSent = false;
            }

            // 8. Respuesta final
            return ApiResponse::success('Pago aplicado y reserva confirmada.', [
                'reservation_id' => (int) $res->id,
                'status' => 'confirmada',
                'payment' => [
                    'payment_id' => (int) $paymentId,
                    'status' => 'pagado',
                    'amount' => $amount,
                    'gateway_ref' => $paymentIntent->id,
                ],
                'email_sent' => $emailSent,
            ], 200);

        } catch (CardException $e) {
            // El pago fue rechazado por el banco
            return ApiResponse::error('Pago rechazado por el banco', ['code' => 'CARD_DECLINED', 'details' => $e->getError()->message], 402);
        } catch (\Throwable $e) {
            // Cualquier otro error (Stripe, base de datos, etc.)
            DB::rollBack();
            return ApiResponse::error('Error interno al aplicar el pago', ['exception' => class_basename($e), 'message' => $e->getMessage()], 500);
        }
    }
}