<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JenisKantor extends Model
{
    use HasFactory;
    protected $table = 'jenis_kantor';
    public $timestamps = false;
    protected $fillable = [
        'id',
        'nama',
    ];
}
