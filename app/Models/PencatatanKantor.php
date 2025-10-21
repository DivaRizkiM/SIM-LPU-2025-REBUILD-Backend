<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PencatatanKantor extends Model
{
    use HasFactory;

    protected $table = 'pencatatan_kantor';
    public $timestamps = false;

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

    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'id', 'id_user');
    }
    public function regional(): HasOne
    {
        return $this->hasOne(Regional::class, 'id', 'id_regional');
    }
    public function provinsi(): HasOne
    {
        return $this->hasOne(Provinsi::class, 'id', 'id_provinsi');
    }
    public function kabupaten(): HasOne
    {
        return $this->hasOne(KabupatenKota::class, 'id', 'id_kabupaten');
    }
    public function kecamatan(): HasOne
    {
        return $this->hasOne(Kecamatan::class, 'id', 'id_kecamatan');
    }
    public function kelurahan(): HasOne
    {
        return $this->hasOne(Kelurahan::class, 'id', 'id_kelurahan');
    }

    public function files(): HasMany
    {
        return $this->hasMany(PencatatanKantorFile::class, 'id_parent', 'id');
    }
}
