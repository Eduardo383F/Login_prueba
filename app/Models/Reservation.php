<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    protected $fillable = [
        'user_id','room_id','check_in','check_out','check_in_time','check_out_time',
        'status','reservation_type','color_code_id','adults','children','total_price','notes'
    ];

    protected $casts = [
        'check_in'  => 'date',
        'check_out' => 'date',
        'check_in_time'  => 'datetime:H:i:s',
        'check_out_time' => 'datetime:H:i:s',
        'total_price' => 'decimal:2',
    ];

    public function user()  { return $this->belongsTo(User::class); }
    public function room()  { return $this->belongsTo(Room::class); }
    public function color() { return $this->belongsTo(ColorCode::class, 'color_code_id'); }
    public function payments() { return $this->hasMany(Payment::class); }
}

