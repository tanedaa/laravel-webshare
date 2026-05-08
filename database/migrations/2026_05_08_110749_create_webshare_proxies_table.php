<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create($this->table(), function (Blueprint $table) {
            $table->id();
            $table->string('proxy_id')->unique();
            $table->string('username');
            $table->text('password');
            $table->string('proxy_address')->index();
            $table->unsignedInteger('port');
            $table->boolean('is_valid')->index();
            $table->string('country_code')->nullable();
            $table->string('city_name')->nullable();
            $table->string('asn_name')->nullable();
            $table->string('asn_number')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->table());
    }

    private function table(): string
    {
        $table = config('webshare.table', 'webshare_proxies');

        return is_string($table) && trim($table) !== '' ? $table : 'webshare_proxies';
    }
};
