<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReservationDocsController extends Controller
{
    // GET /api/reservations/{id}/voucher.pdf
    public function voucher($id, Request $r)
    {
        $res = DB::table('reservations as rv')
            ->join('rooms as rm','rm.id','=','rv.room_id')
            ->join('users as u','u.id','=','rv.user_id')
            ->leftJoin('room_types as rt','rt.id','=','rm.room_type_id')
            ->where('rv.id',$id)
            ->select('rv.*','rm.number as room_number','rt.name as room_type','u.name as guest_name','u.email as email')
            ->first();

        if (!$res || $r->user()->id !== (int)$res->user_id) {
            abort(404);
        }

        // Aquí puedes usar DOMPDF (barryvdh/laravel-dompdf) o tu motor preferido
        $html = view('reservations.voucher', ['res' => $res])->render();

        $pdf = app('dompdf.wrapper'); // tras instalar barryvdh/laravel-dompdf
        $pdf->loadHTML($html)->setPaper('A4', 'portrait');

        return $pdf->stream("reserva-{$id}.pdf");
    }

    // GET /api/reservations/{id}/calendar.ics
    public function calendar($id, Request $r)
    {
        $res = DB::table('reservations')->where('id',$id)->first();
        if (!$res || $r->user()->id !== (int)$res->user_id) {
            abort(404);
        }

        $uid   = "res-{$id}@hotel.local";
        $start = \Carbon\Carbon::parse($res->check_in)->format('Ymd');
        $end   = \Carbon\Carbon::parse($res->check_out)->format('Ymd');
        $ics = "BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Hotel//Booking//ES
BEGIN:VEVENT
UID:{$uid}
DTSTAMP:".now()->utc()->format('Ymd\THis\Z')."
DTSTART;VALUE=DATE:{$start}
DTEND;VALUE=DATE:{$end}
SUMMARY:Estancia en Hotel - Habitación {$res->room_id}
DESCRIPTION:Reserva confirmada. Presenta tu QR en recepción.
END:VEVENT
END:VCALENDAR";

        return response($ics, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"reserva-{$id}.ics\"",
        ]);
    }
}
