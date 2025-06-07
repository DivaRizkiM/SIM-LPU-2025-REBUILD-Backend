<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DashboardProduksiPendapatan extends Model
{
    use HasFactory;

    // Menentukan nama tabel yang digunakan oleh model ini
    protected $table = 'dashboard_produksi_pendapatan';

    // Menentukan kolom yang dapat diisi massal
    protected $fillable = [
        'group_produk',
        'bisnis',
        'status',
        'tanggal',
        'jml_produksi',
        'jml_pendapatan',
        'koefisien',
        'transfer_pricing',
        'verifikasi',
        'verifikasi_by',
        'verifikasi_at',
    ];

    // Menentukan kolom yang seharusnya diubah menjadi tipe timestamp
    protected $casts = [
        'tanggal' => 'integer',
        'verifikasi_at' => 'datetime',
    ];

    // Menentukan format default untuk kolom timestamps (jika diperlukan)
    public $timestamps = false; // jika tabel tidak memiliki kolom `created_at` dan `updated_at`
}
