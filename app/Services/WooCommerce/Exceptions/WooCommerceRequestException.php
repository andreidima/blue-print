<?php

namespace App\Services\WooCommerce\Exceptions;

use Exception;
use Illuminate\Http\Client\Response;

class WooCommerceRequestException extends Exception
{
    public function __construct(
        string $message,
        protected readonly ?Response $response = null
    ) {
        parent::__construct($message);
    }

    public function response(): ?Response
    {
        return $this->response;
    }
}
