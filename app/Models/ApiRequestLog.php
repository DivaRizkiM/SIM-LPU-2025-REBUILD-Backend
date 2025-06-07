<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiRequestLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'komponen',
        'tanggal',
        'ip_address',
        'platform_request',
        'total_records',
        'successful_records',
        'available_records',
        'status',
    ];

    // Define the relationship with ApiRequestPayload
    public function payloads()
    {
        return $this->hasMany(ApiRequestPayload::class);
    }
}
