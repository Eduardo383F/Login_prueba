<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomOccupancyLog extends Model
{
    protected $table = 'room_occupancy_log';
    public $timestamps = false; // solo tiene created_at, sin updated_at “convencional”
    protected $fillable = ['room_id','log_date','reservation_id','status','color_code_id','notes','created_at'];

    protected function casts(): array
    {
        return ['log_date'=>'date'];
    }

    public function room()        { return $this->belongsTo(Room::class); }
    public function reservation() { return $this->belongsTo(Reservation::class); }
    public function color()       { return $this->belongsTo(ColorCode::class, 'color_code_id'); }
}
