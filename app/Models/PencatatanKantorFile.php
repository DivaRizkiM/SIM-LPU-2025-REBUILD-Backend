<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PencatatanKantorFile extends Model
{
    protected $table = 'pencatatan_kantor_file'; // Sesuaikan dengan nama tabel Anda
    public $timestamps = false;
    use HasFactory;
    protected $fillable = [
        'id_parent',
        'nama',
        'file',
        'file_name',
        'file_type',
        'created',
        'updated',

    ];

    /**
     * Get the parent record associated with the kuis.
     */
    public function parent()
    {
        return $this->hasOne(PencatatanKantor::class, 'id', 'id_parent');
    }
}

