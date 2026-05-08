<?php

namespace Tanedaa\LaravelWebShare\Facades;

use Illuminate\Support\Facades\Facade;

class WebShare extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'webshare';
    }
}
