<?php

namespace App\DTOs\Cart;

use App\Models\Cart;

class AddCartItemResult
{
    public function __construct(
        public readonly Cart $cart,
        public readonly bool $created,
    ) {}
}
