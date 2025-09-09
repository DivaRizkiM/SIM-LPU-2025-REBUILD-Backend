<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AlokasiDana extends Model
{
    protected $table = 'alokasi_dana'; // Sesuaikan dengan nama tabel Anda
    public $timestamps = false;
    use HasFactory;
    protected $fillable = [
        'id',
        'id_kpc',
        'kode_dirian',
        'tahun',
        'triwulan',
        'biaya_pegawai',
        'biaya_operasi',
        'biaya_pemeliharaan',
        'biaya_administrasi',
        'biaya_penyusutan',
        'alokasi_dana_lpu',
        'tgl_sinkronisasi',
    ];

    // Jika Anda memiliki relasi dengan model lain, Anda dapat mendefinisikannya di sini
    // Contoh:
    public function kpc()
    {
        return $this->belongsTo(Kpc::class, 'id_kpc');
    }
}
