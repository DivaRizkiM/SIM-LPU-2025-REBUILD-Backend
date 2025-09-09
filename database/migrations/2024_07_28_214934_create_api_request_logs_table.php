<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateApiRequestLogsTable extends Migration
{
    public function up()
    {
        Schema::create('api_request_logs', function (Blueprint $table) {
            $table->id(); // Primary key
            $table->string('komponen'); // Component name
            $table->timestamp('tanggal')->nullable(); // Log date
            $table->string('ip_address')->nullable(); // Client IP address
            $table->string('platform_request')->nullable(); // Platform and browser information
            $table->integer('total_records')->default(0); // Total records pulled
            $table->integer('successful_records')->default(0); // Successfully retrieved records
            $table->string('status')->nullable(); // Status of the API request
            $table->timestamps(); // Created and updated timestamps
        });
    }

    public function down()
    {
        Schema::dropIfExists('api_request_logs');
    }
}

