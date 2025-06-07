<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BiayaAtribusi extends Model
{
    protected $table = 'biaya_atribusi'; // Sesuaikan dengan nama tabel Anda
    public $timestamps = false;
    use HasFactory;
    protected $fillable = [
        'id',
        'id_regional',
        'id_kprk',
        'tahun_anggaran',
        'triwulan',
        'total_biaya',
        'tgl_sinkronisasi',
        'id_status',
        'id_status_kprk',
    ];

    // Jika Anda memiliki relasi dengan model lain, Anda dapat mendefinisikannya di sini
    // Contoh:
    public function regional()
    {
        return $this->belongsTo(Regional::class, 'id_regional');
    }

    public function kprk()
    {
        return $this->belongsTo(Kprk::class, 'id_kprk');
    }

    public function status()
    {
        return $this->belongsTo(Status::class, 'id_status');
    }

    public function statusKprk()
    {
        return $this->belongsTo(StatusKprk::class, 'id_status_kprk');
    }
}
