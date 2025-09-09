<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProduksiNasional extends Model
{
    use HasFactory;
    protected $table = 'produksi_nasional';
    public $timestamps = false;
    protected $fillable = [
        'id',
        'bulan',
        'tahun',
        'jml_produksi',
        'jml_pendapatan',
        'status',
        'produk',
        'bisnis',
    ];
}
