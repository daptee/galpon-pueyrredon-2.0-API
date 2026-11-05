<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VerificationCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public $name;
    public $code;
    public $reason;
    public $minutes;

    public function __construct($name, $code, $reason, $minutes)
    {
        $this->name = $name;
        $this->code = $code;
        $this->reason = $reason;
        $this->minutes = $minutes;
    }

    public function build()
    {
        return $this->subject('Tu código de verificación: ' . $this->code)
            ->markdown('emails.verification-code');
    }
}
