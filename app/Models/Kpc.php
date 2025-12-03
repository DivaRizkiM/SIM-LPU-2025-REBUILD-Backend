<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Kpc extends Model
{
    use HasFactory;

    public $timestamps = false;
    protected $table = 'kpc';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'qr_uuid',
        'id_regional',
        'id_kprk',
        'nomor_dirian',
        'nama',
        'jenis_kantor',
        'alamat',
        'koordinat_longitude',
        'koordinat_latitude',
        'nomor_telpon',
        'nomor_fax',
        'id_provinsi',
        'id_kabupaten_kota',
        'id_kecamatan',
        'id_kelurahan',
        'tipe_kantor',
        'jam_kerja_senin_kamis',
        'jam_kerja_jumat',
        'jam_kerja_sabtu',
        'frekuensi_antar_ke_alamat',
        'frekuensi_antar_ke_dari_kprk',
        'jumlah_tenaga_kontrak',
        'kondisi_gedung',
        'fasilitas_publik_dalam',
        'fasilitas_publik_halaman',
        'lingkungan_kantor',
        'lingkungan_sekitar_kantor',
        'tgl_sinkronisasi',
        'id_user',
        'tgl_update',
        'id_file',
    ];

    public function regional(): BelongsTo
    {
        return $this->belongsTo(Regional::class, 'id_regional', 'id');
    }
    public function kprk(): BelongsTo
    {
        return $this->belongsTo(Kprk::class, 'id_kprk', 'id');
    }
    public function provinsi(): BelongsTo
    {
        return $this->belongsTo(Provinsi::class, 'id_provinsi', 'id');
    }
    public function kabupaten(): BelongsTo
    {
        return $this->belongsTo(KabupatenKota::class, 'id_kabupaten_kota', 'id');
    }
    public function kecamatan(): BelongsTo
    {
        return $this->belongsTo(Kecamatan::class, 'id_kecamatan', 'id');
    }
    public function kelurahan(): BelongsTo
    {
        return $this->belongsTo(Kelurahan::class, 'id_kelurahan', 'id');
    }

    public function pencatatan(): HasMany
    {
        return $this->hasMany(PencatatanKantor::class, 'id_kpc', 'id');
    }
}
