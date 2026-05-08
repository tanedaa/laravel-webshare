<?php

namespace Tanedaa\LaravelWebShare\Models;

use Illuminate\Database\Eloquent\Model;

class Proxy extends Model
{
    protected $table = 'proxies';

    protected $fillable = [
        'proxy_id',
        'username',
        'password',
        'proxy_address',
        'port',
        'is_valid',
        'country_code',
        'city_name',
        'asn_name',
        'asn_number',
    ];

    protected $casts = [
        'is_valid' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
