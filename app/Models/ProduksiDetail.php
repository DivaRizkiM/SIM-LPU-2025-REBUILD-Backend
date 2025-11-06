<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProduksiDetail extends Model
{
    protected $table = 'produksi_detail';

    public $timestamps = false;
    // Attribut yang dapat diisi
    protected $fillable = [
        'id',
        'id_produksi',
        'kode_bisnis',
        'kode_rekening',
        'nama_bulan',
        'nama_rekening',
        'rtarif',
        'tpkirim',
        'jenis_produksi',
        'kategori_produksi',
        'pelaporan',
        'bsu_bruto',
        'bilangan',
        'pelaporan_prognosa',
        'bsu_bruto_prognosa',
        'bilangan_prognosa',
        'verifikasi',
        'total',
        'keterangan',
        'keterangan_prognosa',
        'lampiran',
        'catatan_pemeriksa',
        'id_verifikator',
        'id_produk',
        'tgl_verifikasi',
    ];
    public function produksi()
    {
        return $this->belongsTo(Produksi::class, 'id_produksi');
    }

    public function rekeningBiaya()
    {
        return $this->belongsTo(RekeningBiaya::class, 'id_rekening_biaya');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'id_verifikator');
    }
}
