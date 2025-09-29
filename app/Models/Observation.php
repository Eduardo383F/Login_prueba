<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Observation extends Model
{
    protected $fillable = ['user_id','date','text'];

    protected function casts(): array
    {
        return ['date'=>'datetime'];
    }

    public function user() { return $this->belongsTo(User::class); }
}
