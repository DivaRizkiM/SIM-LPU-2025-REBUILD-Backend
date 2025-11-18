<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BiayaAtribusiDetail extends Model
{
    protected $table = 'biaya_atribusi_detail'; // Sesuaikan dengan nama tabel Anda
    public $timestamps = false;
    use HasFactory;
    protected $fillable = [
        'id',
        'id_biaya_atribusi',
        'id_rekening_biaya',
        'bulan',
        'bilangan',
        'kode_petugas',
        'pelaporan',
        'verifikasi',
        'keterangan',
        'catatan_pemeriksa',
        'lampiran',
        'id_verifikator',
        'tgl_verifikasi',
    ];

    // Jika Anda memiliki relasi dengan model lain, Anda dapat mendefinisikannya di sini
    // Contoh:
    public function biayaAtribusi()
    {
        return $this->belongsTo(BiayaAtribusi::class, 'id_biaya_atribusi');
    }

    public function rekeningBiaya()
    {
        return $this->belongsTo(RekeningBiaya::class, 'id_rekening_biaya');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'id_verifikator');
    }

}
