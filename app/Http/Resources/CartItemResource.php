<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product' => ProductResource::make($this->whenLoaded('product')),
            'quantity' => $this->quantity,
            'unit_price' => $this->whenLoaded('product', fn (Product $product): string => $product->price),
            'line_total' => $this->whenLoaded('product', function (Product $product): string {
                $unitPriceInCents = self::amountToCents((string) $product->price);

                return self::formatCents($unitPriceInCents * $this->quantity);
            }),
        ];
    }

    private static function amountToCents(string $amount): int
    {
        [$wholeUnits, $fractionalUnits] = array_pad(explode('.', $amount, 2), 2, '0');

        return ((int) $wholeUnits * 100) + (int) str_pad($fractionalUnits, 2, '0');
    }

    private static function formatCents(int $amountInCents): string
    {
        return intdiv($amountInCents, 100).'.'.str_pad((string) ($amountInCents % 100), 2, '0', STR_PAD_LEFT);
    }
}
