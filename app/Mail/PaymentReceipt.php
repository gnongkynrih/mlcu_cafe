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
 * The email that carries one paid receipt, with the PDF attached.
 *
 * A Mailable is a class that describes ONE kind of email. Laravel calls
 * three methods to build it:
 *
 *  - envelope()    -> the subject line (and from/reply-to if needed)
 *  - content()     -> the Blade view used as the email body
 *  - attachments() -> the files added to the email
 *
 * It is sent from the success page like this:
 *   Mail::to('guest@example.com')->send(new PaymentReceipt($order));
 *
 * Sending stays on this request, not on a queue. The cashier needs to see
 * straight away whether Gmail accepted it, and a class computer may not
 * be running a queue worker.
 */
class PaymentReceipt extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * "public" makes $order available inside the email's Blade view as $order.
     */
    public function __construct(public Order $order) {}

    /**
     * The subject the client sees in their inbox, e.g. "Receipt #1730803200".
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'MLCU Cafe - Receipt #'.$this->order->order_number,
        );
    }

    /**
     * The email body: resources/views/mail/payment-receipt.blade.php
     */
    public function content(): Content
    {
        return new Content(
            view: 'mail.payment-receipt',
        );
    }

    /**
     * The same till-roll PDF the Download receipt button already builds.
     *
     * Pdf::loadView() turns the Blade view resources/views/pdf/receipt.blade.php
     * into a PDF (dompdf), and output() gives us the file's bytes in memory.
     * Attachment::fromData() attaches those bytes, so no file is saved on disk.
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
