<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VerifikasiBiayaRutinDetail extends Model
{
    protected $table = 'verifikasi_biaya_rutin_detail';
    // protected $primaryKey = 'id'; // Sesuaikan dengan nama primary key pada tabel
    public $timestamps = false;
    // Attribut yang dapat diisi
    protected $fillable = [
        'id',
        'id_verifikasi_biaya_rutin',
        'id_rekening_biaya',
        'bulan',
        'pelaporan',
        'bilangan', 
        'pelaporan_prognosa',
        'bilangan_prognosa',
        'verifikasi',
        'kategori_biaya',
        'keterangan',
        'keterangan_prognosa',
        'lampiran',
        'catatan_pemeriksa',
        'id_verifikator',
        'tgl_verifikasi',
    ];

    public function verifikasiBiayaRutin()
    {
        return $this->belongsTo(VerifikasiBiayaRutin::class, 'id_verifikasi_biaya_rutin');
    }

    public function rekeningBiaya()
    {
        return $this->belongsTo(RekeningBiaya::class, 'id_rekening_biaya');
    }

    public function lampiran()
    {
        return $this->hasOne(VerifikasiBiayaRutinDetailLampiran::class, 'verifikasi_biaya_rutin_detail');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'id_verifikator');
    }
}
