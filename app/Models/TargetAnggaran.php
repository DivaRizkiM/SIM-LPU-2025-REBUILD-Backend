<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TargetAnggaran extends Model
{
    use HasFactory;
    protected $casts = [
        'nominal' => 'integer',
    ];
    protected $table = 'target_anggaran';
    // public $timestamps = false;
    protected $fillable = [
        'tahun',
        'nominal',
    ];

}
