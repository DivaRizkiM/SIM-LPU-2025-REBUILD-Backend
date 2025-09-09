<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PencatatanKantor extends Model
{
    protected $table = 'pencatatan_kantor';
    public $timestamps = false;
    use HasFactory;
    protected $fillable = [
        'id_kpc',
        'id_user',
        'id_regional',
        'id_provinsi',
        'id_kabupaten',
        'id_kecamatan',
        'id_kelurahan',
        'jenis',
        'latitude',
        'longitude',
        'tanggal',
        'created',
        'updated',
    ];

    /**
     * Get the user associated with the pencatatan kantor.
     */
    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'id', 'id_user');
    }

    /**
     * Get the regional associated with the pencatatan kantor.
     */
    public function regional(): HasOne
    {
        return $this->hasOne(Regional::class, 'id', 'id_regional');
    }

    /**
     * Get the provinsi associated with the pencatatan kantor.
     */
    public function provinsi(): HasOne
    {
        return $this->hasOne(Provinsi::class, 'id', 'id_provinsi');
    }

    /**
     * Get the kabupaten associated with the pencatatan kantor.
     */
    public function kabupaten(): HasOne
    {
        return $this->hasOne(Kabupaten::class, 'id', 'id_kabupaten');
    }

    /**
     * Get the kecamatan associated with the pencatatan kantor.
     */
    public function kecamatan(): HasOne
    {
        return $this->hasOne(Kecamatan::class, 'id', 'id_kecamatan');
    }

    /**
     * Get the kelurahan associated with the pencatatan kantor.
     */
    public function kelurahan(): HasOne
    {
        return $this->hasOne(Kelurahan::class, 'id', 'id_kelurahan');
    }
}
