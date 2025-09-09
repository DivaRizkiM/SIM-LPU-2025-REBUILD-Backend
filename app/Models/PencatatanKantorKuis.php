<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PencatatanKantorKuis extends Model
{
    protected $table = 'pencatatan_kantor_kuis'; // Sesuaikan dengan nama tabel Anda
    public $timestamps = false;
    use HasFactory;
    protected $fillable = [
        'id_parent',
        'id_tanya',
        'id_jawab',
        'data',
    ];

    public function jawab()
    {
        return $this->hasOne(KuisJawabKantor::class, 'id', 'id_jawab');
    }

    /**
     * Get the tanya record associated with the kuis.
     */
    public function tanya()
    {
        return $this->hasOne(KuisTanyaKantor::class, 'id', 'id_tanya');
    }

    /**
     * Get the parent record associated with the kuis.
     */
    public function parent()
    {
        return $this->hasOne(PencatatanKantor::class, 'id', 'id_parent');
    }
}

