<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ColorCode extends Model
{
    protected $table = 'color_codes';
    protected $fillable = ['color_name','color_hex','meaning'];
}
