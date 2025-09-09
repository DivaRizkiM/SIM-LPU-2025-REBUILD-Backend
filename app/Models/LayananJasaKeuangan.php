<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LayananJasaKeuangan extends Model
{
    use HasFactory;
    protected $table = 'layanan_jasa_keuangan';
    public $timestamps = false;
    protected $fillable = [
        'id',
        'nama',
    ];
}
