<?php
// app/Models/Testimonial.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Testimonial extends Model
{
    protected $fillable = ['name', 'rating', 'comment', 'is_published', 'user_id'];

    protected $casts = [
        'is_published' => 'boolean',
    ];

    public function user() {
        return $this->belongsTo(User::class);
    }
}
