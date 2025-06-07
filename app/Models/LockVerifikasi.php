<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LockVerifikasi extends Model
{
    use HasFactory;

    /**
     * Nama tabel yang terkait dengan model ini.
     *
     * @var string
     */
    protected $table = 'lock_verifikasis';

    /**
     * Atribut yang dapat diisi secara massal.
     *
     * @var array
     */
    protected $fillable = [
        'tahun',
        'bulan',
        'status',
    ];

    /**
     * Atribut yang harus dianggap sebagai tipe boolean.
     *
     * @var array
     */
    protected $casts = [
        'status' => 'boolean',
    ];
}
