<?php

namespace App\Http\Controllers;

use App\Mail\ContactForm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Exception;
use Throwable;

class ContactFormController extends Controller
{
    public function store(Request $request)
    {
        // Forzar respuesta JSON
        $request->headers->set('Accept', 'application/json');

        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'mail' => 'required|email|max:255',
                'phone' => 'required|string|max:50',
                'comments' => 'required|string|max:2000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'code' => 0,
                    'response' => 'Error de validación',
                    'errors' => $validator->errors()->toArray(),
                ], 422);
            }

            $mailable = new ContactForm(
                $request->name,
                $request->last_name,
                $request->mail,
                $request->phone,
                $request->comments
            );

            // Enviar a la dirección configurada de Galpón
            $to = env('MAIL_NOTIFICATION_TO', env('MAIL_FROM_ADDRESS'));
            Mail::to($to)->send($mailable);

            return response()->json([
                'code' => 1,
                'response' => 'Mensaje enviado correctamente',
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'code' => 0,
                'response' => 'Error al enviar el mensaje',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
