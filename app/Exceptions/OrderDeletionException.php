<?php

namespace App\Exceptions;

class OrderDeletionException extends BusinessRuleException
{
    public function __construct(string $message = 'Order cannot be deleted because it has associated payments.', int $statusCode = 422)
    {
        parent::__construct($message, $statusCode);
    }
}
