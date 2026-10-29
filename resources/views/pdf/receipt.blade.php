<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Receipt #{{ $order->order_number }}</title>
    <style>
        @page { margin: 12px; }
        /* DejaVu Sans ships with dompdf and has the ₹ sign. */
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9px; color: #1c1917; }
        h1 { margin: 0; font-size: 13px; text-align: center; }
        .center { text-align: center; }
        .muted { color: #78716c; }
        .rule { border-top: 1px dashed #a8a29e; margin: 6px 0; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 2px 0; vertical-align: top; }
        .amount { text-align: right; white-space: nowrap; }
        .total td { font-size: 11px; font-weight: bold; padding-top: 4px; }
        .logo{
            max-width: 40px;
            max-height:40px;
        }
    </style>
</head>
<body>
    <img class="logo" src="{{ public_path('logo.png') }}" alt="Logo" style="width: 100px; height: 100px;">
    <h1> MLCU Cafe - Umsawli</h1>
    <p class="center muted">Receipt</p>

    <div class="rule"></div>

    <table>
        <tr><td>Bill no.</td><td class="amount">#{{ $order->order_number }}</td></tr>
        <tr><td>Table</td><td class="amount">{{ $order->cafeTable?->name }}</td></tr>
        @if($order->customer_name)
            <tr><td>Guest</td><td class="amount">{{ $order->customer_name }}</td></tr>
        @endif
        <tr><td>Paid</td><td class="amount">{{ $order->paid_at?->format('d M Y, h:i A') }}</td></tr>
        <tr><td>Payment</td><td class="amount">{{ $order->payment_method === 'upi' ? 'UPI' : ucfirst($order->payment_method) }}</td></tr>
        @if($order->razorpay_payment_id)
            <tr><td>Ref.</td><td class="amount">{{ $order->razorpay_payment_id }}</td></tr>
        @endif
    </table>

    <div class="rule"></div>

    <table>
        @foreach($order->orderItems as $item)
            <tr>
                <td>{{ $item->quantity }} × {{ $item->item_name }}</td>
                <td class="amount">₹{{ number_format((float) $item->subtotal, 2) }}</td>
            </tr>
        @endforeach
    </table>

    <div class="rule"></div>

    <table>
        <tr><td>Subtotal</td><td class="amount">₹{{ number_format((float) $order->subtotal, 2) }}</td></tr>
        @if((float) $order->tax_amount > 0)
            <tr><td>Tax</td><td class="amount">₹{{ number_format((float) $order->tax_amount, 2) }}</td></tr>
        @endif
        @if((float) $order->discount_amount > 0)
            <tr><td>Discount</td><td class="amount">−₹{{ number_format((float) $order->discount_amount, 2) }}</td></tr>
        @endif
        <tr class="total"><td>Total</td><td class="amount">₹{{ number_format((float) $order->total_amount, 2) }}</td></tr>
    </table>

    <div class="rule"></div>

    <p class="center muted">Thank you for visiting!</p>
</body>
</html>
