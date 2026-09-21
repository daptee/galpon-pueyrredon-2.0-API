<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class LogisticsSheetRequest extends Mailable
{
    use Queueable, SerializesModels;

    public $budget;
    public $publicUrl;

    public function __construct($budget, $publicUrl)
    {
        $this->budget = $budget;
        $this->publicUrl = $publicUrl;
    }

    public function build()
    {
        return $this->subject('Datos logísticos de tu evento - Presupuesto Nro ' . $this->budget->id)
            ->markdown('emails.logistics-sheet.request');
    }
}
