<?php
// app/Http/Controllers/TestimonialsController.php
namespace App\Http\Controllers;

use App\Models\Testimonial;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class TestimonialsController extends Controller
{
    public function store(Request $request)
{
    // ⛔️ NO usar: 'min=1' ni 'max=5'
    // ✅ Usar dos puntos: 'min:1', 'max:5'
    $data = $request->validate([
        'rating'  => 'required|integer|min:1|max:5',
        'comment' => 'required|string|max:1000',
    ]);

    $user = $request->user(); // requiere auth:sanctum

    $testimonial = \App\Models\Testimonial::create([
        'user_id'      => $user->id ?? null,  // si tienes la FK
        'name'         => $user->name,        // ignora cualquier "name" del body
        'rating'       => $data['rating'],
        'comment'      => $data['comment'],
        'is_published' => false,
    ]);

    return \App\Support\ApiResponse::success('Testimonio enviado. Pendiente de aprobación.', [
        'id'     => $testimonial->id,
        'name'   => $testimonial->name,
        'rating' => $testimonial->rating,
    ]);
}
}
