<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Contracts\Debug\ShouldntReport;

class OrderConflictException extends Exception implements ShouldntReport
{
    //
}
