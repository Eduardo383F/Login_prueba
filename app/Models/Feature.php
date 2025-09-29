<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Feature extends Model
{
    protected $fillable = ['name'];

    public function rooms() {
        return $this->belongsToMany(Room::class, 'room_feature', 'feature_id', 'room_id');
    }
}

