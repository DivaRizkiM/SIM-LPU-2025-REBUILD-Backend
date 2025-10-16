<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MitraLpu extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'mitra_lpu';

    protected $fillable = [
        'id_kpc',
        'nib',
        'nama_mitra',
        'alamat_mitra',
        'kode_wilayah_kerja',
        'nama_wilayah',
        'nopend',
        'lat',
        'long',
        'nik',
        'namafile',
        'raw',
    ];

    protected $casts = [
        'lat' => 'float',
        'long' => 'float',
        'raw' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $appends = ['coordinates', 'longitude'];

    public function getCoordinatesAttribute(): ?array
    {
        return ($this->lat !== null && $this->long !== null)
            ? ['lat' => (float) $this->lat, 'lng' => (float) $this->long]
            : null;
    }

    public function getLongitudeAttribute(): ?float
    {
        return $this->long !== null ? (float) $this->long : null;
    }


    public function scopeByKpc($query, string $idKpc)
    {
        return $query->where('id_kpc', $idKpc);
    }

    public function scopeSearch($query, ?string $term)
    {
        if (!$term) return $query;

        return $query->where(function ($q) use ($term) {
            $q->where('nama_mitra', 'like', "%{$term}%")
              ->orWhere('nib', 'like', "%{$term}%")
              ->orWhere('nopend', 'like', "%{$term}%")
              ->orWhere('id_kpc', 'like', "%{$term}%")
              ->orWhere('nama_wilayah', 'like', "%{$term}%");
        });
    }

    public function scopeWithinBounds($query, float $swLat, float $swLng, float $neLat, float $neLng)
    {
        return $query->whereBetween('lat', [$swLat, $neLat])
                     ->whereBetween('long', [$swLng, $neLng]);
    }
}
