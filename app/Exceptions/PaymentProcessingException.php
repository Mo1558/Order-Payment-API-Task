<?php

namespace App\Exceptions;

class PaymentProcessingException extends BusinessRuleException
{
    public function __construct(string $message = 'Payment could not be processed.', int $statusCode = 422)
    {
        parent::__construct($message, $statusCode);
    }
}
