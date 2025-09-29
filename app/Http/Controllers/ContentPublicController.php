<?php

namespace App\Http\Controllers;

use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ContentPublicController extends Controller
{
    // GET /api/about
    public function about()
    {
        $page = DB::table('pages')->where('slug','about')->first();

        if (!$page) {
            return ApiResponse::error('Recurso no encontrado', [], 404);
        }

        return ApiResponse::success('Contenido About.', [
            'title'   => $page->title,
            'content' => $page->content,
            'meta'    => $page->meta ? json_decode($page->meta, true) : null,
        ], 200);
    }

    // GET /api/offers
    public function offers(Request $r)
    {
        $rows = DB::table('offers')
            ->where('is_active', 1)
            ->when($r->query('date'), function($q,$d){
                $q->where(function($x) use ($d){
                    $x->whereNull('start_date')->orWhere('start_date','<=',$d);
                })->where(function($x) use ($d){
                    $x->whereNull('end_date')->orWhere('end_date','>=',$d);
                });
            })
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return ApiResponse::success('Listado de ofertas.', [
            'items' => $rows,
            'count' => $rows->count(),
        ], 200);
    }

    // GET /api/offers/{id}
    public function offerShow($id)
    {
        $row = DB::table('offers')->where('id',$id)->first();
        if (!$row) return ApiResponse::error('Recurso no encontrado', [], 404);

        return ApiResponse::success('Detalle de oferta.', (array) $row, 200);
    }

    // GET /api/news
    public function news()
    {
        $rows = DB::table('news')
            ->where('is_published',1)
            ->orderByDesc('published_at')
            ->limit(50)
            ->get();

        return ApiResponse::success('Noticias publicadas.', [
            'items' => $rows, 'count' => $rows->count(),
        ], 200);
    }

    // GET /api/news/{id}
    public function newsShow($id)
    {
        $row = DB::table('news')->where('id',$id)->first();
        if (!$row) return ApiResponse::error('Recurso no encontrado', [], 404);

        return ApiResponse::success('Detalle de noticia.', (array) $row, 200);
    }

    // GET /api/testimonials
    public function testimonials()
    {
        $rows = DB::table('testimonials')
            ->where('is_published',1)
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return ApiResponse::success('Testimonios.', [
            'items' => $rows, 'count' => $rows->count(),
        ], 200);
    }

    // GET /api/events
    public function events()
    {
        $rows = DB::table('events')
            ->where('is_published',1)
            ->orderBy('start_date')
            ->limit(50)
            ->get();

        return ApiResponse::success('Eventos.', [
            'items' => $rows, 'count' => $rows->count(),
        ], 200);
    }

    // GET /api/events/{id}
    public function eventShow($id)
    {
        $row = DB::table('events')->where('id',$id)->first();
        if (!$row) return ApiResponse::error('Recurso no encontrado', [], 404);

        return ApiResponse::success('Detalle de evento.', (array) $row, 200);
        
    }
}
