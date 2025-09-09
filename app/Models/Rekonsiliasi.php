<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rekonsiliasi extends Model
{
    use HasFactory;
    public $timestamps = false;
    protected $table = 'rekonsiliasi';

    protected $fillable = [
        'id',
        'id_penyelenggara',
        'id_kelurahan',
        'id_jenis_kantor',
        'id_kantor',
        'nam_kantor',
        'alamat',
        'longitude',
        'latitude',
    ];

    /**
     * Define the relationship with Penyelenggara model.
     */
    public function penyelenggara()
    {
        return $this->belongsTo(Penyelenggara::class, 'id_penyelenggara');
    }

/**
 * Define the relationship with Kelurahan model.
 */
    public function kelurahan()
    {
        return $this->belongsTo(Kelurahan::class, 'id_kelurahan');
    }

/**
 * Define the relationship with JenisKantor model.
 */
    public function jenisKantor()
    {
        return $this->belongsTo(JenisKantor::class, 'id_jenis_kantor');
    }

/**
 * Define the relationship with Kantor model.
 */

}
