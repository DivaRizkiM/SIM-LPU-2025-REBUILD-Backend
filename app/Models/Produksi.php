<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Produksi extends Model
{
    // use HasFactory;
    protected $table = 'produksi';
    protected $primaryKey = 'id'; // Nama kolom primary key
    public $incrementing = false;  // Non-incrementing
    protected $keyType = 'string';  // Tipe data string
    public $timestamps = false;
    protected $fillable = [
        'id',
        'id_regional',
        'id_kprk',
        'id_kpc',
        'status_kprk',
        'status_regional',
        'tahun_anggaran',
        'triwulan',
        'total_lpu',
        'total_lpu_prognosa',
        'total_lpk',
        'total_lpk_prognosa',
        'total_lbf',
        'total_lbf_prognosa',
        'tgl_sinkronisasi',
        'status_lpu',
        'status_lbf',
        'bulan',
    ];
    // public $incrementing = false;

    public function regional()
    {
        return $this->belongsTo(Regional::class, 'id_regional');
    }

    public function kprk()
    {
        return $this->belongsTo(Kprk::class, 'id_kprk');
    }
    public function kpc()
    {
        return $this->belongsTo(Kpc::class, 'id_kpc');
    }

}
