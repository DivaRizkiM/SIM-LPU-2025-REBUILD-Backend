<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PegawaiDetail extends Model
{
    use HasFactory;

    protected $table = 'pegawai_detail';
    protected $primaryKey = 'id';
    protected $keyType = 'string';


    public $timestamps = false;

    protected $fillable = [
        'id',
        'id_pegawai',
        'id_rekening_biaya',
        'bulan',
        'tahun',
        'pelaporan',
        'verifikasi',
        'nama_file',
        'id_verifikator',
        'tgl_verifikasi',
        'id_status',
        'catatan_pemeriksa',
    ];

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'id_pegawai');
    }


    public function rekeningBiaya()
    {
        return $this->belongsTo(RekeningBiaya::class, 'id_rekening_biaya');
    }


    public function verifikator()
    {
        return $this->belongsTo(User::class, 'id_verifikator');
    }

 
    public function status()
    {
        return $this->belongsTo(Status::class, 'id_status');
    }
}
