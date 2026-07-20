<?php

namespace Amzad\LaravelPurchasable\Enums;

enum TransactionStatus: string
{
    case Pending = 'pending';
    case Success = 'success';
    case Failed = 'failed';
}
