<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KabupatenKota extends Model
{
    use HasFactory;
    protected $table = 'kabupaten_kota'; // Nama tabel di database
    public $timestamps = false;
    protected $fillable = [
        'id',
        'id_provinsi',
        'nama',
    ];

    public function provinsi()
    {
        return $this->belongsTo(Provinsi::class, 'id_provinsi');
    }
}
