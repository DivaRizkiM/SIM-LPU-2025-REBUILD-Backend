<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PencatatanKantorUser extends Model
{
    protected $table = 'pencatatan_kantor_user'; // Sesuaikan dengan nama tabel Anda
    public $timestamps = false;
    use HasFactory;
    protected $fillable = [
        'id_parent',
        'id_user',

    ];

    public function user()
    {
        return $this->hasOne(User::class, 'id', 'id_user');
    }

    /**
     * Get the parent record associated with the kuis.
     */
    public function parent()
    {
        return $this->hasOne(PencatatanKantor::class, 'id', 'id_parent');
    }
}

