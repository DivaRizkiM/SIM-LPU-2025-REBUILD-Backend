<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateApiRequestPayloadLogsTable extends Migration
{
    public function up()
    {
        Schema::create('api_request_payload_logs', function (Blueprint $table) {
            $table->id(); // Primary key
            $table->foreignId('api_request_log_id')->constrained()->onDelete('cascade'); // Foreign key referencing the API request log
            $table->json('payload'); // Store the API response data as JSON
            $table->timestamps(); // Created and updated timestamps
        });
    }

    public function down()
    {
        Schema::dropIfExists('api_request_payload_logs');
    }
}
