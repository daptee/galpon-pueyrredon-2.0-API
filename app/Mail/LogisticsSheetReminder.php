<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class LogisticsSheetReminder extends Mailable
{
    use Queueable, SerializesModels;

    public $budget;
    public $publicUrl;
    public $daysRemaining;

    // Días antes del evento en los que se manda el recordatorio "de
    // presentación" (segundo encabezado, mismo tono los 3).
    public const MILESTONE_DAYS = [15, 10, 7];

    public function __construct($budget, $publicUrl, int $daysRemaining)
    {
        $this->budget = $budget;
        $this->publicUrl = $publicUrl;
        $this->daysRemaining = $daysRemaining;
    }

    public function build()
    {
        $isMilestone = in_array($this->daysRemaining, self::MILESTONE_DAYS, true);

        if ($isMilestone) {
            $subject = "Recordatorio: completá la ficha logística de tu evento (faltan {$this->daysRemaining} días)";
            $intro = "Faltan {$this->daysRemaining} días para tu evento y todavía nos falta información logística "
                . 'para poder organizar la entrega y el retiro.';
        } else {
            $subject = 'Último reclamo: todavía falta completar la ficha logística de tu evento';
            $intro = $this->daysPhrase() . ' y la ficha logística de tu evento sigue incompleta. '
                . 'Por favor completala hoy mismo para que podamos coordinar la entrega y el retiro sin inconvenientes.';
        }

        return $this->subject($subject . ' - Presupuesto Nro ' . $this->budget->id)
            ->markdown('emails.logistics-sheet.reminder', ['intro' => $intro]);
    }

    private function daysPhrase(): string
    {
        if ($this->daysRemaining === 0) {
            return 'Tu evento es hoy';
        }
        if ($this->daysRemaining === 1) {
            return 'Tu evento es mañana';
        }
        return "Faltan solo {$this->daysRemaining} días para tu evento";
    }
}
