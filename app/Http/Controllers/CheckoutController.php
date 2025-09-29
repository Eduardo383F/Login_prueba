<?php

namespace App\Http\Controllers;

use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CheckoutController extends Controller
{
    public function store(Request $r)
    {
        // Validación de entrada
        $r->validate([
            'room_id'                   => 'required|integer|exists:rooms,id',
            'check_in'                  => 'required|date',
            'check_out'                 => 'required|date|after:check_in',
            'adults'                    => 'required|integer|min:1',
            'children'                  => 'required|integer|min:0',
            'card.payment_method_id'    => 'required|string',
            'card.idempotency_key'      => 'required|string|max:64',
        ], [
            'card.payment_method_id.required' => 'Falta el método de pago.',
            'card.idempotency_key.required'   => 'Falta la clave de idempotencia.',
        ]);

        $uid  = $r->user()->id;
        $rid  = (int) $r->room_id;
        $in   = $r->check_in;
        $out  = $r->check_out;
        $ad   = (int) $r->adults;
        $ch   = (int) $r->children;
        $pmId = $r->input('card.payment_method_id');
        $idem = $r->input('card.idempotency_key');

        // Idempotencia: si ya procesamos esta compra, devuelve la confirmación previa
        if ($prev = DB::table('payments')->where('reference', $idem)->first()) {
            return ApiResponse::success('Operación previamente confirmada', [
                'reservation_id' => (int) $prev->reservation_id,
                'payment_id'     => (int) $prev->id,
            ], 200);
        }

        // Calcula precio base por noche
        $base = DB::table('rooms as rm')
            ->join('room_types as rt', 'rt.id', '=', 'rm.room_type_id')
            ->where('rm.id', $rid)
            ->value('rt.base_price');

        $nights = max(0, now()->parse($in)->diffInDays(now()->parse($out)));
        if ($nights <= 0) {
            return ApiResponse::error('Fechas inválidas', [], 422);
        }
        $total = $nights * (float) $base;

        // Autorización de tarjeta (simulada): cambia por tu gateway real (Stripe/OpenPay/Conekta)
        if (!$this->fakeAuthorize($pmId, $total, $idem)) {
            return ApiResponse::error('Pago rechazado', ['code' => 'PAYMENT_FAILED'], 402);
        }

        try {
            DB::beginTransaction();

            // Lock de la habitación para evitar condiciones de carrera
            DB::select('SELECT id FROM rooms WHERE id = ? FOR UPDATE', [$rid]);

            // Verifica traslape con reservas existentes
            $overlap = DB::table('reservations')
                ->where('room_id', $rid)
                ->whereIn('status', ['tentativa','confirmada','completada','no_show'])
                ->where('check_in', '<', $out)
                ->where('check_out','>', $in)
                ->exists();

            if ($overlap) {
                DB::rollBack();
                return ApiResponse::error('Sin disponibilidad para el rango de fechas', ['code' => 'OVERLAP_CONFLICT'], 409);
            }

            // Color de confirmada = azul_cielo
            $colorId = DB::table('color_codes')->where('color_name','azul_cielo')->value('id');

            // Crea reserva confirmada
            $reservationId = DB::table('reservations')->insertGetId([
                'user_id'         => $uid,
                'room_id'         => $rid,
                'check_in'        => $in,
                'check_out'       => $out,
                'status'          => 'confirmada',
                'reservation_type'=> 'web',
                'color_code_id'   => $colorId,
                'adults'          => $ad,
                'children'        => $ch,
                'total_price'     => $total,
                'notes'           => null,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);

            // Captura del pago (simulada). Cambia por la confirmación real del gateway.
            if (!$this->fakeCapture($idem)) {
                DB::rollBack();
                return ApiResponse::error('Pago rechazado', ['code' => 'PAYMENT_FAILED'], 402);
            }

            // Guarda pago
            $paymentId = DB::table('payments')->insertGetId([
                'reservation_id'   => $reservationId,
                'amount'           => $total,
                'payment_method'   => 'tarjeta',
                'payment_date'     => now(),
                'status'           => 'pagado',
                'reference'        => $idem,           // idempotencia
                'gateway_provider' => 'fake',          // cambia a tu gateway
                'gateway_intent_id'=> $idem,
                'gateway_charge_id'=> $idem,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);

            DB::commit();

            $roomNumber = DB::table('rooms')->where('id', $rid)->value('number');

            return ApiResponse::success('Reserva confirmada y pagada', [
                'reservation' => [
                    'id'           => $reservationId,
                    'room_number'  => $roomNumber,
                    'check_in'     => $in,
                    'check_out'    => $out,
                    'adults'       => $ad,
                    'children'     => $ch,
                    'status'       => 'confirmada',
                    'color'        => 'azul_cielo',
                    'total_price'  => $total,
                    'currency'     => 'MXN',
                ],
                'payment' => [
                    'payment_id'  => $paymentId,
                    'status'      => 'pagado',
                    'gateway_ref' => $idem,
                    'captured_at' => now()->toIso8601String(),
                ],
            ], 200);

        } catch (\Throwable $e) {
            DB::rollBack();
            return ApiResponse::error('Error interno del servidor', [
                'exception' => class_basename($e)
            ], 500);
        }
    }

    // --------- Mocks de pasarela (reemplaza por integración real) ----------
    private function fakeAuthorize(string $pm, float $amount, string $key): bool { return true; }
    private function fakeCapture(string $key): bool { return true; }
}
