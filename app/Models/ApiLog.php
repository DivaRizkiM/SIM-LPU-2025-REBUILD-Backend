<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiLog extends Model
{
    use HasFactory;

    protected $table = 'api_log'; // Ganti dengan nama tabel yang sesuai
    public $timestamps = false; // Jika tidak ada kolom timestamp di tabel api log
    protected $fillable = [
        'id',
        'tanggal',
        'komponen',
        'eror_code',
        'ssid',
        'ip_addres',
        'platform_request',
        'identifier_activity',
        'keterangan',
        'sumber',
        'target',
        'status',
    ];
}
