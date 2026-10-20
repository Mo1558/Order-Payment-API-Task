<?php

namespace App\Exceptions;

class UnsupportedPaymentGatewayException extends BusinessRuleException
{
    public function __construct(string $gateway)
    {
        parent::__construct("The payment gateway '{$gateway}' is not supported or is currently disabled.", 422);
    }
}
