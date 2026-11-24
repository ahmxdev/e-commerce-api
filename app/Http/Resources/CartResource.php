<?php

namespace App\Http\Resources;

use App\Models\CartItem;
use Brick\Money\Money;
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

                $subtotal = Money::of(0, 'EGP');
                foreach ($items as $cartItem) {
                    $product = $cartItem->getRelation('product');
                    $unitPrice = Money::of((string) $product->price, 'EGP');
                    $lineTotal = $unitPrice->multipliedBy($cartItem->quantity);
                    $subtotal = $subtotal->plus($lineTotal);
                }

                return $subtotal->getAmount()->toString();
            }),
        ];
    }
}
