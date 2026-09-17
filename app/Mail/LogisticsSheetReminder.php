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

    // Encabezado y urgencia según cuán cerca está el evento.
    private const HEADERS = [
        7 => [
            'subject' => 'Recordatorio: completá la ficha logística de tu evento',
            'intro' => 'Faltan 7 días para tu evento y todavía nos falta información logística para poder organizar la entrega y el retiro.',
        ],
        3 => [
            'subject' => '¡Faltan 3 días! Todavía necesitamos los datos logísticos de tu evento',
            'intro' => 'Tu evento es en 3 días y la ficha logística sigue incompleta. Es importante que la completes cuanto antes para que podamos coordinar todo a tiempo.',
        ],
        1 => [
            'subject' => 'Último aviso: mañana es tu evento y falta completar la ficha logística',
            'intro' => 'Mañana es tu evento y todavía hay datos logísticos pendientes. Por favor completá la ficha hoy mismo para que podamos coordinar la entrega y el retiro sin inconvenientes.',
        ],
    ];

    public function __construct($budget, $publicUrl, int $daysRemaining)
    {
        $this->budget = $budget;
        $this->publicUrl = $publicUrl;
        $this->daysRemaining = $daysRemaining;
    }

    public function build()
    {
        $header = self::HEADERS[$this->daysRemaining] ?? self::HEADERS[7];

        return $this->subject($header['subject'] . ' - Presupuesto Nro ' . $this->budget->id)
            ->markdown('emails.logistics-sheet.reminder', ['intro' => $header['intro']]);
    }
}
