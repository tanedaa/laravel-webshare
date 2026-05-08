<?php

namespace Tanedaa\LaravelWebShare\Exceptions;

use RuntimeException;

class NoValidProxyException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('No valid proxies found in the proxies table. Run: php artisan webshare:update-proxies');
    }
}
