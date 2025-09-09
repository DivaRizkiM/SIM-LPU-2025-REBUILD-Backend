<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserLog extends Model
{
    use HasFactory;

    protected $table = 'user_log'; // Ganti dengan nama tabel yang sesuai
    public $timestamps = false; // Jika tidak ada kolom timestamp di tabel api log
    protected $fillable = [
        'id',
        'timestamp',
        'aktifitas',
        'modul',
        'id_user',
    ];
}
