<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VerifikasiBiayaRutinDetailLampiran extends Model
{
    protected $table = 'verifikasi_biaya_rutin_detail_lampiran';
    public $timestamps = false;
    // Attribut yang dapat diisi
    protected $fillable = [
        'id',
        'id_detail',
        'verifikasi_biaya_rutin_detail',
        'nama_file',
    ];

    public function biayaRutinDetail()
    {
        return $this->belongsTo(VerifikasiBiayaRutinDetail::class, 'verifikasi_biaya_rutin_detail');
    }

}
