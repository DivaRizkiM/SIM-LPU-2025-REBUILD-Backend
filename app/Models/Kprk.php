<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Kprk extends Model
{
    use HasFactory;

    protected $table = 'kprk';
    public $timestamps = false;
    protected $fillable = [
        'id_regional',
        'kode',
        'nama',
        'id_file',
        'id_provinsi',
        'id_kabupaten_kota',
        'id_kecamatan',
        'id_kelurahan',
        'longitude',
        'latitude',
        'tgl_sinkronisasi',
    ];
}
