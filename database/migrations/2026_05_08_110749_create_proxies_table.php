<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('proxies', function (Blueprint $table) {
            $table->id();
            $table->string('proxy_id');
            $table->string('username');
            $table->string('password');
            $table->string('proxy_address')->unique();
            $table->integer('port');
            $table->boolean('is_valid');
            $table->string('country_code');
            $table->string('city_name');
            $table->string('asn_name');
            $table->string('asn_number');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proxies');
    }
};
