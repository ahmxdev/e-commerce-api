<?php

namespace App\Enums\Order;

enum OrderStatusEnum: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function canTransitionTo(self $nextStatus): bool
    {
        return match ($this) {
            self::Pending => in_array($nextStatus, [self::Processing, self::Cancelled], true),
            self::Processing => in_array($nextStatus, [self::Shipped, self::Cancelled], true),
            self::Shipped => $nextStatus === self::Delivered,
            self::Delivered, self::Cancelled => false,
        };
    }
}
