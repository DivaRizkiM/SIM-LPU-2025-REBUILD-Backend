<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RekeningBiaya extends Model
{
    use HasFactory;

    protected $table = 'rekening_biaya';
    public $timestamps = false;
    protected $fillable = [
        'id',
        'kode_rekening',
        'nama',
        'tgl_sinkronisasi',
    ];
}
