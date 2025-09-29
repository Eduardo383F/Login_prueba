<?php

namespace App\Http\Controllers;

use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RoomsPublicController extends Controller
{
    // GET /api/rooms
    public function index(Request $r)
    {
        $r->validate([
            'room_type_id' => 'nullable|integer|exists:room_types,id',
            'adults'       => 'nullable|integer|min:1',
            'children'     => 'nullable|integer|min:0',
            'min_price'    => 'nullable|numeric|min:0',
            'max_price'    => 'nullable|numeric|min:0',
            'floor'        => 'nullable|integer',
        ]);

        $adults   = (int)($r->adults   ?? 1);
        $children = (int)($r->children ?? 0);

        $q = DB::table('rooms as rm')
            ->join('room_types as rt','rt.id','=','rm.room_type_id')
            ->select(
                'rm.id as room_id','rm.number as room_number','rm.floor',
                'rt.id as room_type_id','rt.name as room_type','rt.base_price',
                'rt.max_adults','rt.max_children'
            );

        if ($r->room_type_id) $q->where('rm.room_type_id', $r->room_type_id);
        if ($r->floor)        $q->where('rm.floor', $r->floor);

        // capacidad: si max_* es NULL no limita
        $q->where(function($x) use ($adults){
            $x->whereNull('max_adults')->orWhere('max_adults', '>=', $adults);
        });
        $q->where(function($x) use ($children){
            $x->whereNull('max_children')->orWhere('max_children', '>=', $children);
        });

        if ($r->min_price) $q->where('rt.base_price','>=',$r->min_price);
        if ($r->max_price) $q->where('rt.base_price','<=',$r->max_price);

        $rows = $q->orderBy('rm.number')->get();

        // Amenidades por habitación
        $items = $rows->map(function($x){
            $amenities = DB::table('room_feature as rf')
                ->join('features as f','f.id','=','rf.feature_id')
                ->where('rf.room_id', $x->room_id)
                ->pluck('f.name')->values();

            return [
                'room_id'        => (int)$x->room_id,
                'room_number'    => $x->room_number,
                'floor'          => $x->floor,
                'room_type_id'   => (int)$x->room_type_id,
                'room_type'      => $x->room_type,
                'price_per_night'=> (float)$x->base_price,
                'capacity'       => [
                    'max_adults'   => $x->max_adults,
                    'max_children' => $x->max_children,
                ],
                'amenities'      => $amenities,
                'currency'       => 'MXN',
            ];
        });

        return ApiResponse::success('Listado de habitaciones publicables.', [
            'filters' => [
                'room_type_id' => $r->room_type_id,
                'adults'       => $adults,
                'children'     => $children,
                'min_price'    => $r->min_price,
                'max_price'    => $r->max_price,
                'floor'        => $r->floor,
            ],
            'items' => $items,
            'count' => $items->count(),
        ], 200);
    }
}
