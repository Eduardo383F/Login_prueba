<?php

namespace App\Http\Controllers;

use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AvailabilityController extends Controller
{
    public function index(Request $r)
    {
        // 1) Validación
        $r->validate([
            'check_in'      => 'required|date',
            'check_out'     => 'required|date|after:check_in',
            'adults'        => 'nullable|integer|min:1',
            'children'      => 'nullable|integer|min:0',
            'room_type_id'  => 'nullable|integer|exists:room_types,id',
        ], [
            'check_in.required'  => 'La fecha de entrada es obligatoria.',
            'check_out.required' => 'La fecha de salida es obligatoria.',
            'check_out.after'    => 'La fecha de salida debe ser mayor a la entrada.',
        ]);

        $checkIn  = $r->check_in;
        $checkOut = $r->check_out;
        $adults   = (int) ($r->adults   ?? 1);
        $children = (int) ($r->children ?? 0);

        // 2) Noches
        $nights = Carbon::parse($checkIn)->diffInDays(Carbon::parse($checkOut));
        if ($nights <= 0) {
            return ApiResponse::error('Fechas o capacidad inválidas', [
                'details' => ['nights' => ['Debe ser al menos 1 noche']]
            ], 422);
        }

        // 3) Query de disponibilidad (incluye cancellation_policy)
        $rooms = DB::table('rooms as rm')
            ->join('room_types as rt', 'rt.id', '=', 'rm.room_type_id')
            ->whereNotExists(function ($q) use ($checkIn, $checkOut) {
                $q->from('room_blockages as b')
                  ->whereColumn('b.room_id', 'rm.id')
                  ->where('b.start_date', '<', $checkOut)
                  ->where('b.end_date',   '>', $checkIn);
            })
            ->whereNotExists(function ($q) use ($checkIn, $checkOut) {
                $q->from('reservations as rv')
                  ->whereColumn('rv.room_id', 'rm.id')
                  ->whereIn('rv.status', ['tentativa','confirmada','completada','no_show'])
                  ->where('rv.check_in',  '<', $checkOut)
                  ->where('rv.check_out', '>', $checkIn);
            })
            ->when($r->room_type_id, fn($q) => $q->where('rm.room_type_id', $r->room_type_id))
            ->where(function($q) use ($adults) {
                $q->whereNull('rt.max_adults')->orWhere('rt.max_adults', '>=', $adults);
            })
            ->where(function($q) use ($children) {
                $q->whereNull('rt.max_children')->orWhere('rt.max_children', '>=', $children);
            })
            ->selectRaw('
                rm.id   as room_id,
                rm.number as room_number,
                rm.floor as floor,
                rt.id   as room_type_id,
                rt.name as room_type,
                rt.base_price as price_per_night,
                rt.cancellation_policy
            ')
            ->orderBy('rm.number')
            ->get();

        // Si no hay rooms, responde directo
        if ($rooms->isEmpty()) {
            return ApiResponse::success('Disponibilidad calculada', [
                'check_in'  => $checkIn,
                'check_out' => $checkOut,
                'adults'    => $adults,
                'children'  => $children,
                'nights'    => $nights,
                'results'   => [],
            ], 200);
        }

        // 4) Cargar amenities EN BLOQUE para evitar N+1
        $roomIds = $rooms->pluck('room_id')->all();
        $amenitiesByRoom = DB::table('room_feature as rf')
            ->join('features as f', 'f.id', '=', 'rf.feature_id')
            ->whereIn('rf.room_id', $roomIds)
            ->select('rf.room_id', 'f.name')
            ->get()
            ->groupBy('room_id')
            ->map(fn($g) => $g->pluck('name')->values());

        // 5) Armar respuesta final con amenities y policy
        $results = $rooms->map(function ($x) use ($nights, $amenitiesByRoom) {
            $price = (float) $x->price_per_night;
            return [
                'room_id'             => (int) $x->room_id,
                'room_number'         => $x->room_number,
                'floor'               => $x->floor,
                'room_type_id'        => (int) $x->room_type_id,
                'room_type'           => $x->room_type,
                'price_per_night'     => $price,
                'nights'              => $nights,
                'total_price'         => $nights * $price,
                'currency'            => 'MXN',
                'amenities'           => ($amenitiesByRoom[$x->room_id] ?? collect())->all(),
                'cancellation_policy' => $x->cancellation_policy, // viene del join
            ];
        });

        return ApiResponse::success('Disponibilidad calculada', [
            'check_in'  => $checkIn,
            'check_out' => $checkOut,
            'adults'    => $adults,
            'children'  => $children,
            'nights'    => $nights,
            'results'   => $results,
        ], 200);
    }
}
