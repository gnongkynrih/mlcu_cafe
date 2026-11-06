{{--
    The body of the receipt email (built by app/Mail/PaymentReceipt.php).
    $order comes from the Mailable's public $order property.
    Email apps ignore most CSS files, so styles are written inline.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Receipt #{{ $order->order_number }}</title>
</head>
<body style="font-family: sans-serif; color: #1c1917;">
    <h1>MLCU Cafe</h1>
    <p>Receipt <strong>#{{ $order->order_number }}</strong></p>
    <p>Table {{ $order->cafeTable?->name }}</p>
    @if($order->customer_name)
        <p>Guest {{ $order->customer_name }}</p>
    @endif
    <p>Paid {{ $order->paid_at?->format('d M Y, h:i A') }}</p>

    <table cellpadding="4" cellspacing="0">
        @foreach($order->orderItems as $item)
            <tr>
                <td>{{ $item->quantity }} × {{ $item->item_name }}</td>
                <td>₹{{ number_format((float) $item->subtotal, 2) }}</td>
            </tr>
        @endforeach
    </table>

    <p><strong>Total ₹{{ number_format((float) $order->total_amount, 2) }}</strong></p>
    <p>The PDF receipt is attached.</p>
</body>
</html>
