<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('kpc', function (Blueprint $table) {
            $table->uuid('qr_uuid')->nullable()->unique()->after('id');
            $table->index('qr_uuid');
        });
    }

    public function down()
    {
        Schema::table('kpc', function (Blueprint $table) {
            $table->dropColumn('qr_uuid');
        });
    }
};