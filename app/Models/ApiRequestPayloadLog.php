<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiRequestPayloadLog extends Model
{
    use HasFactory;

    protected $fillable = [
	    'id',
        'api_request_log_id',
        'payload',
    ];

    // Define the relationship with ApiRequestLog
    public function apiRequestLog()
    {
        return $this->belongsTo(ApiRequestLog::class);
    }
}
