<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Kecamatan extends Model
{
    use HasFactory;
    protected $table = 'kecamatan';
    public $timestamps = false;
    protected $fillable = [
        'id',
        'id_provinsi',
        'id_kabupaten_kota',
        'nama',
    ];

    /**
     * Define the relationship with KabupatenKota model.
     */
    public function kabupatenKota()
    {
        return $this->belongsTo(KabupatenKota::class, 'id_kabupaten_kota');
    }
}
