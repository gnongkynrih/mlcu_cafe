<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

/**
 * Downloads the PDF receipt for a paid order.
 *
 * Open orders have no receipt yet, so they answer 404.
 */
class OrderReceiptController extends Controller
{
    public function __invoke(Order $order): Response
    {
        abort_unless($order->status === 'paid', 404);

        $order->load([
            'cafeTable',
            'orderItems' => fn ($query) => $query->where('status', '!=', 'cancelled')->orderBy('id'),
        ]);

        return Pdf::loadView('pdf.receipt', ['order' => $order])
            ->setPaper([0, 0, 226.77, 600]) // 80 mm wide, like a till roll.
            ->download('receipt-'.$order->order_number.'.pdf');
    }
}
