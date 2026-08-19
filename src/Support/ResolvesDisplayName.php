<?php

namespace Amzad\LaravelPurchasable\Support;

use Closure;
use Illuminate\Support\Str;

/**
 * Resolves a friendly display name for a buyer/purchasable morph, deferring
 * to an optional method on the related model (e.g. getBuyerDisplayName(),
 * getPurchasableDisplayName()) when it's implemented, and falling back to
 * the raw "ClassName #id" shape when it isn't — the same duck-typed,
 * non-breaking pattern Concerns\Purchasable already uses for
 * getPurchasableCurrency().
 *
 * $resolveRelated is only invoked when $type resolves to a loadable class,
 * so a stale/orphaned morph type (the related class was renamed or removed)
 * degrades to the "ClassName #id" fallback instead of a fatal error —
 * MorphTo::getResults() throws immediately if the class doesn't exist, so
 * the related model can never be resolved eagerly up front.
 */
trait ResolvesDisplayName
{
    protected function resolveDisplayName(?string $type, int|string|null $id, Closure $resolveRelated, string $method): string
    {
        if ($type !== null && class_exists($type)) {
            $related = $resolveRelated();

            if ($related !== null && method_exists($related, $method)) {
                return (string) $related->{$method}();
            }
        }

        return $type !== null
            ? Str::headline(class_basename($type)).' #'.$id
            : __('Unknown');
    }
}
