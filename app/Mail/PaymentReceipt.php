<?php

namespace App\Mail;

use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The letter that carries one paid bill.
 *
 * Sending stays on this request, not on a queue. The cashier needs to see
 * straight away whether Gmail accepted it, and a class computer may not
 * be running a queue worker.
 */
class PaymentReceipt extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Receipt #'.$this->order->order_number,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.payment-receipt',
        );
    }

    /**
     * The same till-roll PDF the Download receipt button already builds.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $pdf = Pdf::loadView('pdf.receipt', ['order' => $this->order])
            ->setPaper([0, 0, 226.77, 600])
            ->output();

        return [
            Attachment::fromData(fn () => $pdf, 'receipt-'.$this->order->order_number.'.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
