<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PetugasKPC extends Model
{
    use HasFactory;
    protected $table = 'kpc_petugas'; // Sesuaikan dengan nama tabel Anda
    public $timestamps = false;
    protected $fillable = [
        'id',
        'id_kpc',
        'nippos',
        'nama_petugas',
        'pangkat',
        'masa_kerja',
        'jabatan',
        'id_user',
        'tgl_update',
    ];

    // Jika Anda memiliki relasi dengan model lain, Anda dapat mendefinisikannya di sini
    // Contoh:
    // public function user()
    // {
    //     return $this->belongsTo(User::class, 'id_user');
    // }
}
