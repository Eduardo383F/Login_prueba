<?php

namespace App\Http\Controllers;

use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReservationController extends Controller
{
    /**
     * POST /api/reservations
     * Crea una pre-reserva (estado "tentativa") con datos de contacto y extras.
     */
    public function store(Request $request)
    {
        // 1) Validación
        $fields = $request->validate([
            'room_id'   => 'required|integer|exists:rooms,id',
            'check_in'  => 'required|date|after_or_equal:today',
            'check_out' => 'required|date|after:check_in',
            'adults'    => 'nullable|integer|min:1',
            'children'  => 'nullable|integer|min:0',

            // nuevos campos de contacto / peticiones
            'phone'            => 'nullable|string|max:20',
            'address'          => 'nullable|string',
            'special_requests' => 'nullable|string',

            // extras
            'extras'               => 'nullable|array',
            'extras.*.extra_id'    => 'required_with:extras|exists:extras,id',
            'extras.*.quantity'    => 'nullable|integer|min:1',
        ]);

        // 2) Datos base
        $adults   = (int)($fields['adults']   ?? 1);
        $children = (int)($fields['children'] ?? 0);
        $checkIn  = Carbon::parse($fields['check_in'])->toDateString();
        $checkOut = Carbon::parse($fields['check_out'])->toDateString();
        $nights   = Carbon::parse($checkIn)->diffInDays(Carbon::parse($checkOut));
        if ($nights <= 0) {
            return ApiResponse::error('Fechas inválidas: noches debe ser > 0', [], 422);
        }

        // 3) Traer room + base_price
        $room = DB::table('rooms as rm')
            ->join('room_types as rt', 'rt.id', '=', 'rm.room_type_id')
            ->where('rm.id', $fields['room_id'])
            ->select('rm.id','rm.number','rm.room_type_id','rt.name as room_type','rt.base_price')
            ->first();

        if (!$room) {
            return ApiResponse::error('Recurso no encontrado', [], 404);
        }

        // 4) Verificar traslape por seguridad (además del trigger)
        $overlap = DB::table('reservations')
            ->where('room_id', $room->id)
            ->whereIn('status', ['tentativa','confirmada','completada','no_show'])
            ->where('check_in', '<',  $checkOut)
            ->where('check_out','>',  $checkIn)
            ->exists();
        if ($overlap) {
            return ApiResponse::error('La habitación ya está reservada en ese rango de fechas', ['code' => 'ROOM_OVERLAP'], 409);
        }

        // 5) Cálculos
        $roomTotal = (float)$room->base_price * $nights;
        $extrasIn  = $fields['extras'] ?? [];

        // 6) Transacción: reserva + extras
        try {
            DB::beginTransaction();

            // 6.1 Insertar pre-reserva (guardamos room_total en total_price)
            $resId = DB::table('reservations')->insertGetId([
                'user_id'          => $request->user()->id,
                'room_id'          => $room->id,
                'check_in'         => $checkIn,
                'check_out'        => $checkOut,
                'adults'           => $adults,
                'children'         => $children,
                'phone'            => $fields['phone']            ?? null,
                'address'          => $fields['address']          ?? null,
                'special_requests' => $fields['special_requests'] ?? null,
                'status'           => 'tentativa',
                'total_price'      => $roomTotal,  // guarda solo habitación
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);

            // 6.2 Insertar extras (si vienen)
            $extrasTotal = 0;
            $extrasOut   = [];

            if (!empty($extrasIn)) {
                // Traemos precios actuales de los extras en un batch
                $catalog = DB::table('extras')
                    ->whereIn('id', collect($extrasIn)->pluck('extra_id'))
                    ->where('is_active', 1)
                    ->get()
                    ->keyBy('id');

                foreach ($extrasIn as $e) {
                    $extraId = (int)$e['extra_id'];
                    $qty     = max(1, (int)($e['quantity'] ?? 1));
                    if (!$catalog->has($extraId)) continue;

                    $unit = (float)$catalog[$extraId]->price;
                    $tot  = $qty * $unit;

                    DB::table('reservation_extra')->updateOrInsert(
                        ['reservation_id' => $resId, 'extra_id' => $extraId],
                        ['quantity' => $qty, 'unit_price' => $unit, 'total_price' => $tot]
                    );

                    $extrasTotal += $tot;
                    $extrasOut[] = [
                        'extra_id'    => $extraId,
                        'name'        => $catalog[$extraId]->name,
                        'quantity'    => $qty,
                        'unit_price'  => $unit,
                        'total_price' => $tot,
                    ];
                }
            }

            DB::commit();

        } catch (\Throwable $e) {
            DB::rollBack();
            return ApiResponse::error('Error al crear la reserva', ['exception' => class_basename($e)], 500);
        }

        // 7) Respuesta
        $grand = $roomTotal + ($extrasTotal ?? 0);

        return ApiResponse::success('Reserva creada (tentativa).', [
            'reservation_id' => (int)$resId,
            'room_number'    => $room->number,
            'room_type'      => $room->room_type,
            'check_in'       => $checkIn,
            'check_out'      => $checkOut,
            'nights'         => $nights,
            'adults'         => $adults,
            'children'       => $children,
            'status'         => 'tentativa',
            // desglose
            'room_total'     => $roomTotal,
            'extras'         => $extrasOut ?? [],
            'extras_total'   => $extrasTotal ?? 0,
            'grand_total'    => $grand,
        ], 201);
    }

    // (tus métodos show() e index() pueden quedarse como los tienes)

    /**
     * GET /api/reservations/{id}
     * Muestra detalles de la reserva (solo dueño)
     */
    public function show($id, Request $r)
    {
        $res = DB::table('reservations as rv')
            ->join('rooms as rm', 'rm.id', '=', 'rv.room_id')
            ->leftJoin('room_types as rt', 'rt.id', '=', 'rm.room_type_id')
            ->join('users as u', 'u.id', '=', 'rv.user_id')
            ->where('rv.id', $id)
            ->select(
                'rv.*',
                'rm.number as room_number',
                'rt.name as room_type',
                'u.name as guest_name',
                'u.email as guest_email'
            )
            ->first();

        if (!$res) {
            return ApiResponse::error('Recurso no encontrado', [], 404);
        }

        if ($r->user()->id !== (int)$res->user_id) {
            return ApiResponse::error('No autorizado para esta reserva', ['code' => 'FORBIDDEN'], 403);
        }

        $nights = max(0, now()->parse($res->check_in)->diffInDays(now()->parse($res->check_out)));

        return ApiResponse::success('Detalles de la reserva.', [
            'reservation_id' => (int)$res->id,
            'guest_name'     => $res->guest_name,
            'guest_email'    => $res->guest_email,
            'room_number'    => $res->room_number,
            'room_type'      => $res->room_type,
            'check_in'       => $res->check_in,
            'check_out'      => $res->check_out,
            'nights'         => $nights,
            'adults'         => (int)$res->adults,
            'children'       => (int)$res->children,
            'total_price'    => (float)$res->total_price,
            'status'         => $res->status,
        ], 200);
    }


// app/Http/Controllers/ReservationController.php

public function index(Request $r)
{
    $status   = $r->query('status');     // opcional
    $upcoming = $r->boolean('upcoming'); // opcional

    $q = DB::table('reservations as rv')
        ->join('rooms as rm','rm.id','=','rv.room_id')
        ->leftJoin('room_types as rt','rt.id','=','rm.room_type_id')
        ->leftJoin('color_codes as cc','cc.id','=','rv.color_code_id')
        ->where('rv.user_id', $r->user()->id)
        ->select(
            'rv.id',
            'rv.check_in','rv.check_out',
            'rv.adults','rv.children',
            'rv.status','rv.total_price',
            'rv.check_in_time','rv.check_out_time',
            'rm.number as room_number',
            'rt.name as room_type',
            'cc.color_name','cc.color_hex'
        )
        ->orderByDesc('rv.id');

    if ($status)   $q->where('rv.status',$status);
    if ($upcoming) $q->whereDate('rv.check_in','>=', now()->toDateString());

    $rows = $q->limit(100)->get();

    return ApiResponse::success('Listado de reservas del usuario.', [
        'items' => $rows->map(function($x){
            return [
                'reservation_id'  => (int)$x->id,
                'room_number'     => $x->room_number,
                'room_type'       => $x->room_type,
                'check_in'        => $x->check_in,
                'check_out'       => $x->check_out,
                'nights'          => max(0, \Carbon\Carbon::parse($x->check_in)->diffInDays($x->check_out)),
                'adults'          => (int)$x->adults,
                'children'        => (int)$x->children,
                'status'          => $x->status,
                'total_price'     => (float)$x->total_price,
                'check_in_time'   => $x->check_in_time,
                'check_out_time'  => $x->check_out_time,
                'color'           => [
                    'name' => $x->color_name,
                    'hex'  => $x->color_hex,
                ],
            ];
        }),
        'count' => $rows->count(),
    ], 200);
}


}
