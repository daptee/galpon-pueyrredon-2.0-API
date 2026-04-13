<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BudgetNotification extends Mailable
{
    use Queueable, SerializesModels;

    public $budget;
    public $pdfPath;

    public function __construct($budget, $pdfPath)
    {
        $this->budget = $budget;
        $this->pdfPath = $pdfPath;
    }

    public function build()
    {
        $subject = 'Nuevo presupuesto generado - Nro ' . $this->budget->id . ' - ' . ($this->budget->client_name ?? ($this->budget->client->name ?? 'Cliente'));

        return $this->subject($subject)
            ->markdown('emails.budget.notification')
            ->attach($this->pdfPath, [
                'as' => 'Presupuesto-GalponPueyrredon-' . $this->budget->id . '.pdf',
                'mime' => 'application/pdf',
            ]);
    }
}
