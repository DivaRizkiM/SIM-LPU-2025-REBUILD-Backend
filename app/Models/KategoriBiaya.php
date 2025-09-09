<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KategoriBiaya extends Model
{
    use HasFactory;
    protected $table = 'jenis_biaya';
    public $timestamps = false;
    protected $fillable = [
        'id',
        'nama',
    ];
}
