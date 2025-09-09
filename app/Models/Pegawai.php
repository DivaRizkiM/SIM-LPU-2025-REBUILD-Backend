<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pegawai extends Model
{
    use HasFactory;

    protected $table = 'pegawai'; 
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'nama',
        'jabatan',
        'nama_bagian',
        'id_regional',
    ];


    public function regional()
    {
        return $this->belongsTo(Regional::class, 'id_regional');
    }

    public function laporanPegawai()
    {
        return $this->hasMany(PegawaiDetail::class, 'id_pegawai');
    }
}
