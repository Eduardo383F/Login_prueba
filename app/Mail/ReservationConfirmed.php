<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ReservationConfirmed extends Mailable
{
    use Queueable, SerializesModels;

    public array $payload;

    public function __construct(array $payload) { $this->payload = $payload; }

    public function build()
    {
        return $this->subject('Confirmación de reserva')
            ->view('emails.reservation_confirmed');
    }
}
