<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Contracts\Debug\ShouldntReport;

class ProductImageConflictException extends Exception implements ShouldntReport {}
