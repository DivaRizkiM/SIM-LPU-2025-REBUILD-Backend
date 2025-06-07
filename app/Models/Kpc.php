<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Kpc extends Model
{
    use HasFactory;
    public $timestamps = false;
    protected $table = 'kpc';
    protected $primaryKey = 'id'; // Nama kolom primary key
    public $incrementing = false;  // Non-incrementing
    protected $keyType = 'string';  // Tipe data string
    protected $fillable = [
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
}
