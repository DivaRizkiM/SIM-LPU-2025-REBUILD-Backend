<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VerifikasiLtk extends Model
{
    use HasFactory;

    protected $table = 'verifikasi_ltk';
    protected $fillable = [
        'id',
        'tahun',
        'bulan',
        'kode_rekening',
        'nama_rekening',
        'mtd_akuntansi',
        'verifikasi_akuntansi',
        'biaya_pso',
        'verifikasi_pso',
        'mtd_biaya_pos',
        'mtd_biaya_hasil',
        'proporsi_rumus',
        'verifikasi_proporsi',
        'id_status',
        'nama_file',
        'catatan_pemeriksa',
        'kategori_cost',
        'keterangan',
        'jenis'
    ];
    public $incrementing = false;  // Non-incrementing
    protected $keyType = 'string';  // Tipe data string
    public $timestamps = false;


    public function rekeningBiaya()
    {
        return $this->belongsTo(RekeningBiaya::class, 'kode_rekening');
    }
    public function status()
    {
        return $this->belongsTo(Status::class, 'id_status');
    }
}
