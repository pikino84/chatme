<?php

namespace App\Exceptions;

use Exception;

class BillingLimitException extends Exception
{
    public function __construct(string $message = 'Billing limit reached for your current plan.')
    {
        parent::__construct($message, 403);
    }
}
