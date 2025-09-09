<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KuisTanyaKantor extends Model
{
    protected $table = 'kuis_tanya_kantor'; // Sesuaikan dengan nama tabel Anda
    public $timestamps = false;
    use HasFactory;
    protected $fillable = [
        'id',
        'id_tanya',
        'nama',
        'persen',
        'form_sort',
        'form_page',
    ];
}
