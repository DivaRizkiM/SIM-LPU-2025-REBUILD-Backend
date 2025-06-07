<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KuisJawabKantor extends Model
{
    protected $table = 'kuis_jawab_kantor'; // Sesuaikan dengan nama tabel Anda
    public $timestamps = false;
    use HasFactory;
    protected $fillable = [
        'id',
        'id_tanya',
        'nama',
        'skor',
        'urut',
    ];

    public function tanya()
    {
        return $this->hasOne(KuisTanyaKantor::class, 'id', 'id_tanya');
    }
}
