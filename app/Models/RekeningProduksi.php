<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RekeningProduksi extends Model
{
    use HasFactory;

    protected $table = 'rekening_produksi';
    public $timestamps = false;
    protected $fillable = [
        'kode_rekening',
        'nama',
        'id_produk',
        'nama_produk',
        'id_tipe_bisnis',
        'tgl_sinkronisasi',
    ];
}
