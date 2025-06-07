<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Kelurahan extends Model
{
    use HasFactory;
    public $timestamps = false;
    protected $table = 'kelurahan';

    protected $fillable = [
        'id',
        'id_provinsi',
        'id_kabupaten_kota',
        'id_kecamatan',
        'nama',
    ];

    /**
     * Define the relationship with Kecamatan model.
     */
    public function kecamatan()
    {
        return $this->belongsTo(Kecamatan::class, 'id_kecamatan');
    }
}
