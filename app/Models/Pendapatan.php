<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pendapatan extends Model
{
    use HasFactory;

    protected $table = 'pendapatan';

    protected $primaryKey = 'id';

    protected $fillable = [
        'id',
        'id_regional',
        'id_kprk',
        'id_kpc',
        'tahun',
        'triwulan',
        'tgl_sinkronisasi',
        'kategori_pendapatan',
        'id_rekening',
        'bulan',
        'rtarif',
        'tpkirim',
        'pelaporan_outgoing',
        'pelaporan_incoming',
        'pelaporan_sisa_layanan',
    ];
}
