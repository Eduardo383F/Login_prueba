<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomType extends Model
{
    protected $table = 'room_types';
    protected $fillable = ['name','description','base_price','max_adults','max_children'];

    public function rooms() { return $this->hasMany(Room::class); }
}
