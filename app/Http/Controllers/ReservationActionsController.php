<?php

namespace App\Http\Controllers;

use App\Mail\ReservationConfirmed;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\DB;

class ReservationActionsController extends Controller
{
    // POST /api/reservations/{id}/send-confirmation
    public function sendConfirmation($id, Request $r)
    {
        // 1) Buscar reserva
        $res = DB::table('reservations as rv')
            ->join('rooms as rm', 'rm.id', '=', 'rv.room_id')
            ->join('users as u', 'u.id', '=', 'rv.user_id')
            ->where('rv.id', $id)
            ->select('rv.*', 'rm.number as room_number', 'u.email as email', 'u.name as guest_name')
            ->first();

        if (!$res) return ApiResponse::error('Recurso no encontrado', [], 404);
        if ($res->status !== 'confirmada') {
            return ApiResponse::error('La reserva debe estar confirmada para enviar el QR', ['code'=>'RESERVATION_NOT_CONFIRMED'], 409);
        }

        // ✅ (1) Asegurar que el usuario autenticado ES el dueño de la reserva
        if ($r->user()->id !== (int) $res->user_id) {
            return ApiResponse::error('No autorizado para esta reserva', ['code' => 'FORBIDDEN'], 403);
        }

        // 2) Generar token QR firmado con expiración (igual que ya tenías)
        $expiresAt = now()->addDays(30);
        $tokenData = [
            'reservation_id' => (int) $res->id,
            'user_id'        => (int) $res->user_id,
            'exp'            => $expiresAt->timestamp,
        ];
        $qrToken = encrypt(json_encode($tokenData));

        // 3) Generar **SVG** (no usa GD/Imagick) y crear data URI
        $svg = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')
                ->size(220)
                ->generate($qrToken);

        $dataUri = 'data:image/svg+xml;base64,' . base64_encode($svg);


        // 4) Enviar correo (MAIL_MAILER=log para pruebas)
        $nights = max(0, now()->parse($res->check_in)->diffInDays(now()->parse($res->check_out)));
        $payload = [
            'guest_name'   => $res->guest_name,
            'room_number'  => $res->room_number,
            'check_in'     => $res->check_in,
            'check_out'    => $res->check_out,
            'nights'       => $nights,
            'total_price'  => (float) $res->total_price,
            'currency'     => 'MXN',
            'qr_data_uri'  => $dataUri,
            'qr_expires_at'=> $expiresAt->toDateTimeString(),
        ];

        Mail::to($res->email)->send(new ReservationConfirmed($payload));

        return ApiResponse::success('Confirmación enviada', [
            'reservation_id' => (int) $res->id,
            'email_to'       => $res->email,
            'qr_token_preview'=> substr($qrToken, 0, 24).'…', // solo para debug
            'qr_token'         => $qrToken,
            'sent_at'        => now()->toIso8601String(),
        ], 200);
    }

    // POST /api/checkin/scan
    public function scanQr(Request $r)
{
    // 1) Validación del body
    $r->validate(['qr_token' => 'required|string']);

    // 2) Desencriptar token y validar expiración
    try {
        $data = json_decode(decrypt($r->qr_token), true);
    } catch (\Throwable $e) {
        return \App\Support\ApiResponse::error('Token de acceso inválido o alterado', ['code' => 'INVALID_QR'], 401);
    }

    if (!isset($data['exp']) || now()->timestamp > (int) $data['exp']) {
        return \App\Support\ApiResponse::error('Token de acceso inválido o expirado', ['code' => 'EXPIRED_QR'], 401);
    }

    // 3) Buscar reserva y verificar estado
    $res = DB::table('reservations as rv')
        ->join('rooms as rm', 'rm.id', '=', 'rv.room_id')
        ->join('users as u', 'u.id', '=', 'rv.user_id')
        ->where('rv.id', $data['reservation_id'])
        ->select('rv.*', 'rm.number as room_number', 'u.name as guest_name')
        ->first();

    if (!$res) {
        return \App\Support\ApiResponse::error('Recurso no encontrado', [], 404);
    }

    // Debe estar confirmada y sin check-in registrado
    if ($res->status !== 'confirmada') {
        return \App\Support\ApiResponse::error('La reserva no está en estado válido para check-in', ['code' => 'INVALID_STATUS'], 409);
    }
    if (!is_null($res->check_in_time)) {
        return \App\Support\ApiResponse::error('La reserva ya tiene check-in registrado', ['code' => 'ALREADY_CHECKED_IN'], 409);
    }

    // (Opcional) Validar que hoy esté dentro del rango de estancia (+/- tolerancias)
    // $today = now()->toDateString();
    // if ($today < $res->check_in || $today > $res->check_out) { ... }

    try {
        DB::beginTransaction();

        // 4) Marcar check-in (solo hora, NO cambiamos el status de la reserva para no tocar el ENUM)
        DB::table('reservations')->where('id', $res->id)->update([
            'check_in_time' => now()->format('H:i:s'),
            'updated_at'    => now(),
        ]);

        // 5) Marcar habitación como ocupada
        DB::table('rooms')->where('id', $res->room_id)->update([
            'status'     => 'ocupada',
            'updated_at' => now(),
        ]);

        // 6) Registrar en room_occupancy_log (si tu tabla está creada)
        DB::table('room_occupancy_log')->updateOrInsert(
            [
                'room_id'  => $res->room_id,
                'log_date' => now()->toDateString(),
            ],
            [
                'reservation_id' => $res->id,
                'status'         => 'ocupada',
                'color_code_id'  => DB::table('color_codes')->where('color_name','azul_cielo')->value('id'),
                'notes'          => 'Check-in por QR',
                'created_at'     => now(),
            ]
        );

        DB::commit();

        return \App\Support\ApiResponse::success('Check-in realizado correctamente', [
            'reservation_id' => (int) $res->id,
            'room_number'    => $res->room_number,
            'guest_name'     => $res->guest_name,
            'check_in'       => $res->check_in,
            'check_out'      => $res->check_out,
            'checked_in_at'  => now()->toDateTimeString(),
            'room_status'    => 'ocupada',
        ], 200);

    } catch (\Throwable $e) {
        DB::rollBack();
        return \App\Support\ApiResponse::error('Error interno al registrar check-in', [
            'exception' => class_basename($e)
        ], 500);
    }
}

// app/Http/Controllers/ReservationActionsController.php

public function checkout($id, \Illuminate\Http\Request $r)
{
    // Traer reserva con joins útiles
    $res = \Illuminate\Support\Facades\DB::table('reservations as rv')
        ->join('rooms as rm', 'rm.id', '=', 'rv.room_id')
        ->join('users as u', 'u.id', '=', 'rv.user_id')
        ->where('rv.id', $id)
        ->select('rv.*', 'rm.number as room_number', 'u.id as owner_id', 'u.name as guest_name')
        ->first();

    if (!$res) {
        return \App\Support\ApiResponse::error('Recurso no encontrado', [], 404);
    }

    // Solo dueño puede hacer checkout
    if ($r->user()->id !== (int) $res->owner_id) {
        return \App\Support\ApiResponse::error('No autorizado para esta reserva', ['code' => 'FORBIDDEN'], 403);
    }

    // Debe haber hecho check-in y estar confirmada
    if ($res->status !== 'confirmada') {
        return \App\Support\ApiResponse::error('La reserva no está en estado válido para checkout', ['code' => 'INVALID_STATUS'], 409);
    }
    if (is_null($res->check_in_time)) {
        return \App\Support\ApiResponse::error('No se ha registrado el check-in de esta reserva', ['code' => 'MISSING_CHECKIN'], 409);
    }
    if (!is_null($res->check_out_time)) {
        return \App\Support\ApiResponse::error('Esta reserva ya tiene check-out registrado', ['code' => 'ALREADY_CHECKED_OUT'], 409);
    }

    try {
        \Illuminate\Support\Facades\DB::beginTransaction();

        // 1) Marcar check_out_time y cerrar la reserva
        \Illuminate\Support\Facades\DB::table('reservations')->where('id', $res->id)->update([
            'check_out_time' => now()->format('H:i:s'),
            'status'         => 'completada', // cerramos la estancia
            'updated_at'     => now(),
        ]);

        // 2) Liberar habitación
        \Illuminate\Support\Facades\DB::table('rooms')->where('id', $res->room_id)->update([
            'status'     => 'disponible',
            'updated_at' => now(),
        ]);

        // 3) Log de ocupación del día (opcional)
        \Illuminate\Support\Facades\DB::table('room_occupancy_log')->updateOrInsert(
            ['room_id' => $res->room_id, 'log_date' => now()->toDateString()],
            [
                'reservation_id' => $res->id,
                'status'         => 'disponible',
                'color_code_id'  => \Illuminate\Support\Facades\DB::table('color_codes')->where('color_name','gris')->value('id'),
                'notes'          => 'Check-out del huésped',
                'created_at'     => now(),
            ]
        );

        \Illuminate\Support\Facades\DB::commit();

        return \App\Support\ApiResponse::success('Check-out realizado correctamente', [
            'reservation_id' => (int) $res->id,
            'room_number'    => $res->room_number,
            'guest_name'     => $res->guest_name,
            'checked_out_at' => now()->toDateTimeString(),
            'room_status'    => 'disponible',
            'reservation_status' => 'completada',
        ], 200);

    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\DB::rollBack();
        return \App\Support\ApiResponse::error('Error interno al registrar check-out', [
            'exception' => class_basename($e)
        ], 500);
    }
}


}
