<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sale Report</title>
</head>
<body>
    <h1>Sale Report</h1>
    <table>
        <thead>
            <tr>
                @if($reportType=='details')
                    <th>Transaction ID</th>
                @endif
                <th>Date</th>
                <th>Total Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($orders as $order)
                <tr>
                     @if($reportType=='details')
                        <td> {{ $order->razorpay_payment_id }}</td>
                    @endif
                    <td>{{ $order->paid_at->format('d-m-Y') }}</td>
                    <td>{{ $order->total_amount }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>