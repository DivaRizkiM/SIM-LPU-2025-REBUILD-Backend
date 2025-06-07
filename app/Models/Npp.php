<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Npp extends Model
{
    protected $table = 'npp'; // Sesuaikan dengan nama tabel Anda
    public $timestamps = false;
    use HasFactory;
    protected $casts = [
        'bsu' => 'integer',
    ];
    protected $fillable = [
        'id',
        'id_rekening_biaya',
        'bulan',
        'tahun',
        'bsu',
        'verifikasi',
        'nama_file',
        'id_verifikator',
        'tgl_verifikasi',
        'id_status',
        'catatan_pemeriksa',
    ];
    public function rekeningBiaya()
    {
        return $this->belongsTo(RekeningBiaya::class, 'id_rekening_biaya');
    }
    public function status()
    {
        return $this->belongsTo(Status::class, 'id_status');
    }
}
