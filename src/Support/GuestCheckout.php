<?php

namespace Amzad\LaravelPurchasable\Support;

use Amzad\LaravelPurchasable\Exceptions\AlreadyPurchasedException;
use Amzad\LaravelPurchasable\Exceptions\GuestCheckoutException;
use Amzad\LaravelPurchasable\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Shared guest handling for CreateOrder and CreateMultiItemOrder: validates
 * the guest details, builds the order's guest columns, and applies the
 * repurchase / pending guards by guest email instead of by buyer model.
 */
class GuestCheckout
{
    /**
     * @param  array{email?: string, name?: string|null, phone?: string|null}  $guest
     * @return array{guest_email: string, guest_name: string|null, guest_phone: string|null, access_token: string}
     *
     * @throws GuestCheckoutException
     */
    public static function attributes(array $guest): array
    {
        if (! config('purchasable.allow_guest_checkout', false)) {
            throw GuestCheckoutException::disabled();
        }

        $email = Order::normalizeGuestEmail((string) ($guest['email'] ?? ''));

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw GuestCheckoutException::invalidEmail();
        }

        return [
            'guest_email' => $email,
            'guest_name' => $guest['name'] ?? null,
            'guest_phone' => $guest['phone'] ?? null,
            'access_token' => Str::random(64),
        ];
    }

    /**
     * @throws AlreadyPurchasedException
     */
    public static function guardAgainstDuplicate(string $email, Model $purchasable): void
    {
        $orders = fn () => Order::query()
            ->forGuest($email)
            ->where('purchasable_type', $purchasable->getMorphClass())
            ->where('purchasable_id', $purchasable->getKey());

        if (
            (! config('purchasable.allow_repurchase', false) && $orders()->paid()->exists())
            || (! config('purchasable.allow_multiple_pending_orders', false) && $orders()->pending()->exists())
        ) {
            throw AlreadyPurchasedException::forGuest($email, $purchasable);
        }
    }
}
