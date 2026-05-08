<?php

namespace Tanedaa\LaravelWebShare\Models;

use Illuminate\Database\Eloquent\Model;

class Proxy extends Model
{
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

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'password' => 'encrypted',
        'port' => 'integer',
        'is_valid' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function getTable(): string
    {
        $table = config('webshare.table', 'webshare_proxies');

        return is_string($table) && trim($table) !== '' ? $table : 'webshare_proxies';
    }
}
