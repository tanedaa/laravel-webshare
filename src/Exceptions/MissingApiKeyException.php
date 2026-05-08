<?php

namespace Tanedaa\LaravelWebShare\Exceptions;

use RuntimeException;

class MissingApiKeyException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Missing WebShare API key. Set WEBSHARE_API_KEY in your environment or webshare.api_key in config.');
    }
}
