<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    protected $fillable = ['number','room_type_id','status','floor','description'];

    public function type()  { return $this->belongsTo(RoomType::class, 'room_type_id'); }
    public function reservations() { return $this->hasMany(Reservation::class); }
    public function blockages()    { return $this->hasMany(RoomBlockage::class); }

}
