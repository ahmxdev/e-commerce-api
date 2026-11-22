<?php

namespace App\Http\Resources;

use App\Models\CartItem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
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
            'items' => CartItemResource::collection($this->whenLoaded('items')),
            'subtotal' => $this->whenLoaded('items', function (Collection $items): ?string {
                if ($items->contains(fn (CartItem $cartItem): bool => ! $cartItem->relationLoaded('product'))) {
                    return null;
                }

                $subtotalInCents = $items->sum(function (CartItem $cartItem): int {
                    $product = $cartItem->getRelation('product');
                    $unitPriceInCents = self::amountToCents((string) $product->price);

                    return $unitPriceInCents * $cartItem->quantity;
                });

                return self::formatCents($subtotalInCents);
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
