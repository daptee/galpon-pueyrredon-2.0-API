<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BudgetCreated extends Mailable
{
    use Queueable, SerializesModels;

    public $budget;
    public $pdfPath;
    public $user;

    public function __construct($budget, $pdfPath, $user)
    {
        $this->budget = $budget;
        $this->pdfPath = $pdfPath;
        $this->user = $user;
    }

    public function build()
    {
        $subject = 'Galpón Pueyrredon - Nro ' . $this->budget->id . ' - ' . \Carbon\Carbon::parse($this->budget->date_event)->format('d-M') . ' - ' . $this->budget->place->name;

        return $this->subject($subject)
                    ->markdown('emails.budget.created')
                    ->attach($this->pdfPath, [
                        'as' => 'Presupuesto-GalponPueyrredon-' . $this->budget->id . '.pdf',
                        'mime' => 'application/pdf',
                    ]);
    }
}
