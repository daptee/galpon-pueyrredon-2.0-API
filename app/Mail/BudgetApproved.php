<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BudgetApproved extends Mailable
{
    use Queueable, SerializesModels;

    public $budget;
    public $pdfPath;
    public $logisticsSheetUrl;
    // Solo los presupuestos hijos (con id_budget) reemplazan a uno anterior:
    // el padre directo.
    public $replacedBudgetId;

    public function __construct($budget, $pdfPath, $logisticsSheetUrl = null)
    {
        $this->budget = $budget;
        $this->pdfPath = $pdfPath;
        $this->logisticsSheetUrl = $logisticsSheetUrl;
        $this->replacedBudgetId = $budget->id_budget ?: null;
    }

    public function build()
    {
        $budgetNumber = str_pad($this->budget->id, 5, '0', STR_PAD_LEFT);
        $subject = 'CONFIRMACION – GP' . $budgetNumber . ' – ' . \Carbon\Carbon::parse($this->budget->date_event)->format('d-M') . ' – ' . $this->budget->place->name;

        return $this->subject($subject)
            ->markdown('emails.budget.approved')
            ->attach($this->pdfPath, [
                'as' => 'Presupuesto-GalponPueyrredon-' . $this->budget->id . '.pdf',
                'mime' => 'application/pdf',
            ]);
    }
}
