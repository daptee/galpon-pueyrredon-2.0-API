<?php

namespace App\Http\Controllers;

use App\Http\Audith\AudithHelper;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Exception;
use App\Http\Token\TokenService;
use Log;
use Mail;
use Str;

class AuthController extends Controller
{
    protected $tokenService;

    public function __construct(TokenService $tokenService)
    {
        $this->tokenService = $tokenService;
    }

    public function login(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'password' => 'required|string',
            ]);

            $user = User::where('email', $request->email)->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                return ApiResponse::create('Credenciales no válidas', 401, 'Unauthorized', [
                    'request' => $request,
                    'module' => 'auth',
                    'endpoint' => 'Login',
                ]);
            }

            // Generar el token usando el servicio TokenService
            $token = $this->tokenService->generateToken($user);

            $user->load('userType.status', 'client.status', 'theme', 'status');

            return ApiResponse::login('Inicio de sesión exitoso', 201, $user, $token, [
                'request' => $request,
                'module' => 'auth',
                'endpoint' => 'Login',
            ]);

        } catch (Exception $e) {
            return ApiResponse::create('Error al iniciar sesión', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'auth',
                'endpoint' => 'Login',
            ]);
        }
    }
    /**
     * Get the authenticated User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function me()
    {
        return response()->json(auth()->user());
    }

    public function resetPassword(Request $request)
    {

        Log::info("holaaaaaaaaaaaaaaaa");
        try {
            // Validar la entrada
            $request->validate([
                'email' => 'required|email|exists:users,email',
            ]);

            // Buscar el usuario
            $user = User::where('email', $request->email)->first();

            if (!$user) {
                return ApiResponse::create('Usuario no encontrado', 404, 'Not Found', [
                    'request' => $request,
                    'module' => 'auth',
                    'endpoint' => 'resetPassword',
                ]);
            }

            // Generar nueva contraseña aleatoria
            $newPassword = Str::random(8);

            // Asignar la nueva contraseña encriptada
            $user->password = Hash::make($newPassword);
            $user->save();

            // Enviar email con la nueva contraseña
            Mail::send('emails.reset_password', ['user' => $user, 'password' => $newPassword], function ($message) use ($user) {
                $message->to($user->email)
                    ->subject('Nueva contraseña generada');
            });

            return ApiResponse::create('Se ha enviado una nueva contraseña al correo del usuario', 201, $request->email, [
                'request' => $request,
                'module' => 'auth',
                'endpoint' => 'resetPassword',
            ]);

        } catch (\Exception $e) {
            return ApiResponse::create('Error al restablecer la contraseña', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'auth',
                'endpoint' => 'resetPassword',
            ]);
        }
    }

    public function setPassword(Request $request)
    {
        try {
            // Validar la entrada
            $request->validate([
                'current_password' => 'required|string',
                'new_password' => 'required|string|min:8|confirmed',
            ]);

            // Obtener el usuario autenticado
            $user = auth()->user();

            // Verificar si la contraseña actual coincide
            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'message' => 'La contraseña actual es incorrecta',
                ], 400);
            }

            // Asignar la nueva contraseña (se encripta automáticamente en el modelo)
            $user->password = $request->new_password;
            $user->save();

            return response()->json([
                'message' => 'Contraseña actualizada con éxito',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar la contraseña',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


}
