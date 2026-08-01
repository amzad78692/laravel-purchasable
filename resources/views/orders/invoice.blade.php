@php
    $statusLabel = $order->status->label();
    $statusBadgeClass = match ($order->status->getValue()) {
        'paid' => 'badge-success',
        'failed', 'cancelled' => 'badge-danger',
        'refunded', 'partially_refunded' => 'badge-warning',
        default => 'badge-gray',
    };
    $isRtl = app()->getLocale() === 'ar';
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">

<head>
    <meta charset="utf-8">
    <title>{{ __('Invoice :number', ['number' => $order->order_number]) }}</title>
    <style>
        @page {
            margin: 34px 38px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 12px;
            color: #1f2937;
        }

        .accent-bar {
            height: 5px;
            background-color: #4f46e5;
            border-radius: 3px;
            margin-bottom: 22px;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 22px;
        }

        .header-table td {
            border: none;
            vertical-align: top;
            padding: 0;
        }

        .brand-name {
            font-size: 21px;
            font-weight: bold;
            color: #111827;
            margin: 0;
        }

        .brand-tagline {
            font-size: 10px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 3px;
        }

        .meta-table {
            width: auto;
            margin-left: auto;
            border-collapse: collapse;
        }

        .meta-table td {
            border: none;
            padding: 2px 0 2px 18px;
            font-size: 11px;
            white-space: nowrap;
        }

        .meta-label {
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 9px;
        }

        .meta-value {
            font-weight: bold;
            color: #111827;
            text-align: right;
        }

        .badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 9px;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-success {
            background-color: #dcfce7;
            color: #166534;
        }

        .badge-warning {
            background-color: #fef3c7;
            color: #92400e;
        }

        .badge-danger {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .badge-gray {
            background-color: #f3f4f6;
            color: #374151;
        }

        .badge-primary {
            background-color: #e0e7ff;
            color: #3730a3;
        }

        .section-title {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #6b7280;
            font-weight: bold;
            margin: 26px 0 8px;
        }

        table.data {
            width: 100%;
            border-collapse: collapse;
        }

        table.data th {
            background-color: #f9fafb;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #6b7280;
            text-align: left;
            padding: 8px 10px;
            border-bottom: 2px solid #e5e7eb;
        }

        table.data td {
            padding: 10px;
            border-bottom: 1px solid #f3f4f6;
            font-size: 11px;
        }

        .text-right {
            text-align: right;
        }

        .text-muted {
            color: #6b7280;
        }

        table.totals {
            width: 100%;
            margin-top: 14px;
            border-collapse: collapse;
        }

        table.totals td {
            border: none;
            padding: 4px 10px;
            font-size: 11px;
        }

        table.totals tr.final td {
            font-size: 14px;
            font-weight: bold;
            color: #111827;
            border-top: 2px solid #111827;
            padding-top: 10px;
        }

        .meta-box {
            background-color: #f9fafb;
            border-radius: 6px;
            padding: 12px 14px;
            margin-top: 8px;
        }

        .meta-box .row {
            font-size: 10px;
            margin-bottom: 4px;
        }

        .meta-box .row:last-child {
            margin-bottom: 0;
        }

        .meta-box strong {
            color: #374151;
        }

        .footer {
            margin-top: 36px;
            padding-top: 12px;
            border-top: 1px solid #e5e7eb;
            font-size: 9px;
            color: #9ca3af;
            text-align: center;
        }

        @if ($isRtl)
            .meta-table {
                margin-left: 0;
                margin-right: auto;
            }

            .meta-table td {
                padding: 2px 18px 2px 0;
            }

            .meta-value {
                text-align: left;
            }

            table.data th {
                text-align: right;
            }

            .text-right {
                text-align: left;
            }
        @endif
    </style>
</head>

<body>
    <div class="accent-bar"></div>

    <table class="header-table">
        <tr>
            <td>
                <div class="brand-name">{{ config('app.name') }}</div>
                <div class="brand-tagline">{{ __('Order Invoice') }}</div>
            </td>
            <td class="text-right">
                <table class="meta-table">
                    <tr>
                        <td class="meta-label">{{ __('Order #') }}</td>
                        <td class="meta-value">{{ $order->order_number }}</td>
                    </tr>
                    <tr>
                        <td class="meta-label">{{ __('Status') }}</td>
                        <td class="meta-value">
                            <span class="badge {{ $statusBadgeClass }}">{{ $statusLabel }}</span>
                        </td>
                    </tr>
                    <tr>
                        <td class="meta-label">{{ __('Order date') }}</td>
                        <td class="meta-value">{{ $order->created_at->toFormattedDateString() }}</td>
                    </tr>
                    @if ($order->paid_at)
                        <tr>
                            <td class="meta-label">{{ __('Paid date') }}</td>
                            <td class="meta-value">{{ $order->paid_at->toFormattedDateString() }}</td>
                        </tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    @if ($order->transactions->isNotEmpty())
        <div class="section-title">{{ __('Payment history') }}</div>
        <table class="data">
            <tr>
                <th>{{ __('Transaction #') }}</th>
                <th>{{ __('Type') }}</th>
                <th>{{ __('Gateway') }}</th>
                <th>{{ __('Reference') }}</th>
                <th>{{ __('Status') }}</th>
                <th class="text-right">{{ __('Amount') }}</th>
                <th>{{ __('Date') }}</th>
            </tr>
            @foreach ($order->transactions as $transaction)
                <tr>
                    <td>{{ $transaction->transaction_number }}</td>
                    <td>
                        <span
                            class="badge {{ $transaction->type === \Amzad\LaravelPurchasable\Enums\TransactionType::Refund ? 'badge-warning' : 'badge-primary' }}">
                            {{ $transaction->type->label() }}
                        </span>
                    </td>
                    <td>{{ $transaction->gateway }}</td>
                    <td class="text-muted">{{ $transaction->gateway_transaction_id ?? '—' }}</td>
                    <td>
                        <span
                            class="badge {{ match ($transaction->status) {
                                \Amzad\LaravelPurchasable\Enums\TransactionStatus::Success => 'badge-success',
                                \Amzad\LaravelPurchasable\Enums\TransactionStatus::Failed => 'badge-danger',
                                \Amzad\LaravelPurchasable\Enums\TransactionStatus::Pending => 'badge-gray',
                            } }}">
                            {{ $transaction->status->label() }}
                        </span>
                    </td>
                    <td class="text-right">{{ number_format((float) $transaction->amount, 2) }}
                        {{ $transaction->currency }}</td>
                    <td class="text-muted">
                        {{ ($transaction->paid_at ?? $transaction->created_at)->toFormattedDateString() }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    <table class="totals">
        <tr>
            <td class="text-right text-muted">{{ __('Subtotal') }}</td>
            <td class="text-right" style="width: 160px;">{{ number_format((float) $order->amount, 2) }}
                {{ $order->currency }}</td>
        </tr>
        @if (bccomp($order->refundedAmount(), '0', 3) > 0)
            <tr>
                <td class="text-right text-muted">{{ __('Refunded') }}</td>
                <td class="text-right">-{{ number_format((float) $order->refundedAmount(), 2) }}
                    {{ $order->currency }}</td>
            </tr>
        @endif
        <tr class="final">
            <td class="text-right">{{ __('Total paid') }}</td>
            <td class="text-right">{{ number_format((float) $order->amount - (float) $order->refundedAmount(), 2) }}
                {{ $order->currency }}</td>
        </tr>
    </table>

    @if (filled($order->meta))
        <div class="section-title">{{ __('Additional details') }}</div>
        <div class="meta-box">
            @foreach ($order->meta as $key => $value)
                <div class="row">
                    <strong>{{ \Illuminate\Support\Str::headline((string) $key) }}:</strong>
                    {{ is_array($value) ? json_encode($value, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) : $value }}
                </div>
            @endforeach
        </div>
    @endif

    <div class="footer">
        {{ __('Generated on :date', ['date' => now()->toDayDateTimeString()]) }} &middot; {{ config('app.name') }}
    </div>
</body>

</html>
