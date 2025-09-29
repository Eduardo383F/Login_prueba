<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomCleaning extends Model
{
    protected $table = 'room_cleanings';
    protected $fillable = ['room_id','date','staff_id','status','notes'];

    protected $casts = ['date' => 'date'];

    public function room()  { return $this->belongsTo(Room::class); }
    public function staff() { return $this->belongsTo(User::class, 'staff_id'); }
}
