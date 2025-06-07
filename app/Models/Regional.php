<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Regional extends Model
{
    use HasFactory;

    protected $table = 'regional';
    public $timestamps = false;
    protected $fillable = [
        'kode',
        'nama',
        'id_file',
        'id_provinsi',
        'id_kabupaten_kota',
        'id_kecamatan',
        'id_kelurahan',
        'longitude',
        'latitude',
    ];
}
