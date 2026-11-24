<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Contracts\Debug\ShouldntReport;

class InsufficientProductStockException extends Exception implements ShouldntReport
{
    public function __construct(
        string $message = 'The requested quantity exceeds available stock.',
    ) {
        parent::__construct($message);
    }
}
