<?php

namespace Amzad\LaravelPurchasable\Filament\Concerns;

/**
 * Gateway/host-supplied strings (gateway names, transaction IDs, webhook
 * payloads) aren't guaranteed to be valid UTF-8. Livewire's own AJAX
 * response encoding has no tolerance for invalid bytes anywhere in the
 * rendered page state — one bad value breaks every interaction on that
 * page, not just the field it's in. Everything host/gateway-supplied that
 * this package renders in Filament should be normalized through here.
 */
trait SanitizesUtf8
{
    protected static function sanitizeUtf8(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return mb_convert_encoding($value, 'UTF-8', 'UTF-8');
    }
}
