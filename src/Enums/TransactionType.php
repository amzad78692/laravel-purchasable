<?php

namespace Amzad\LaravelPurchasable\Enums;

enum TransactionType: string
{
    case Charge = 'charge';
    case Refund = 'refund';
}
