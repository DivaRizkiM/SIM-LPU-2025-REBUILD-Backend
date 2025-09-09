<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LayananKurir extends Model
{
    use HasFactory;

    protected $table = 'layanan_kurir';
    public $timestamps = false;
    protected $fillable = [
        'id',
        'nama',
    ];
}
