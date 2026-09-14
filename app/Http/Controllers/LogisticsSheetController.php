<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Mail\LogisticsSheetRequest;
use App\Models\Budget;
use App\Models\LogisticsSheet;
use App\Services\MailService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LogisticsSheetController extends Controller
{
    /**
     * Obtiene la ficha logística de un presupuesto (la crea, con un token
     * nuevo, si todavía no existe) y le envía por mail al cliente el link
     * público para que la complete. Llamarlo de nuevo más adelante reenvía
     * el mismo link (sirve como "reenvío" del reclamo).
     */
    public function getOrCreate(Request $request, $idBudget)
    {
        try {
            $budget = Budget::find($idBudget);

            if (!$budget) {
                return ApiResponse::create('Presupuesto no encontrado', 404, ['error' => 'Presupuesto no encontrado'], [
                    'request' => $request,
                    'module' => 'logistics sheet',
                    'endpoint' => 'Obtener o crear ficha logística',
                ]);
            }

            if (!$budget->client_mail) {
                return ApiResponse::create('El presupuesto no tiene un email de cliente cargado', 422, ['error' => 'El presupuesto no tiene un email de cliente cargado'], [
                    'request' => $request,
                    'module' => 'logistics sheet',
                    'endpoint' => 'Obtener o crear ficha logística',
                ]);
            }

            $logisticsSheet = LogisticsSheet::firstOrCreate(
                ['id_budget' => $idBudget],
                ['token' => (string) Str::uuid()]
            );

            $logisticsSheet->load('budget', 'eventType');

            $publicUrl = rtrim(env('LOGISTICS_SHEET_FRONTEND_URL', ''), '/') . '/ficha-logistica/' . $logisticsSheet->token;

            $mailTo = config('app.env') === 'testing' || config('app.env') === 'local'
                ? env('MAIL_REDIRECT_TO', env('MAIL_FROM_ADDRESS'))
                : $budget->client_mail;

            MailService::sendAndSave($mailTo, new LogisticsSheetRequest($budget, $publicUrl));

            $result = $logisticsSheet->toArray();
            $result['public_url'] = $publicUrl;
            $result['mail_sent_to'] = $mailTo;

            return ApiResponse::create('Ficha logística enviada al cliente correctamente', 200, $result, [
                'request' => $request,
                'module' => 'logistics sheet',
                'endpoint' => 'Obtener o crear ficha logística',
            ]);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al enviar la ficha logística', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'logistics sheet',
                'endpoint' => 'Obtener o crear ficha logística',
            ]);
        }
    }
}
