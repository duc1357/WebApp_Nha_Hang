<?php

class PaymentStateService {
    public const ORDER_PENDING = 'pending';
    public const ORDER_PAID = 'paid';
    public const ORDER_CANCELLED = 'cancelled';

    public const BOOKING_PENDING = 'pending';
    public const BOOKING_AWAITING_PAYMENT = 'awaiting_payment';
    public const BOOKING_CONFIRMED = 'confirmed';
    public const BOOKING_ARRIVED = 'arrived';
    public const BOOKING_COMPLETED = 'completed';
    public const BOOKING_CANCELLED = 'cancelled';

    public const PAYMENT_PENDING = 'pending';
    public const PAYMENT_PARTIAL = 'partial';
    public const PAYMENT_PAID = 'paid';

    public static function orderStatuses(): array {
        return [self::ORDER_PENDING, self::ORDER_PAID, self::ORDER_CANCELLED];
    }

    public static function bookingStatuses(): array {
        return [
            self::BOOKING_PENDING,
            self::BOOKING_AWAITING_PAYMENT,
            self::BOOKING_CONFIRMED,
            self::BOOKING_ARRIVED,
            self::BOOKING_COMPLETED,
            self::BOOKING_CANCELLED,
        ];
    }

    public static function bookingPaymentStatuses(): array {
        return [self::PAYMENT_PENDING, self::PAYMENT_PARTIAL, self::PAYMENT_PAID];
    }

    public static function isPaidOrderStatus(string $status): bool {
        return $status === self::ORDER_PAID;
    }

    public static function isPaidBookingPaymentStatus(string $status): bool {
        return in_array($status, [self::PAYMENT_PARTIAL, self::PAYMENT_PAID], true);
    }
}
