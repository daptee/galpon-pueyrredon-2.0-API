<?php

namespace App\Services;

use App\Http\Responses\ApiResponse;
use App\Mail\VerificationCodeMail;
use Illuminate\Http\JsonResponse;
use App\Models\User;
use App\Models\VerificationCode;
use Illuminate\Support\Facades\Mail;

/**
 * Códigos de verificación de 6 dígitos enviados por email.
 *
 * Se usan para el login y para acciones sensibles (cambiar email, cambiar
 * contraseña, desactivar la verificación). Solo aplican si el usuario tiene
 * `two_factor_enabled` activo.
 */
class VerificationCodeService
{
    public const PURPOSE_LOGIN = 'login';
    public const PURPOSE_SENSITIVE = 'sensitive';

    /** Minutos de validez del código. */
    public const EXPIRATION_MINUTES = 10;

    /** Segundos mínimos entre dos envíos para el mismo usuario y propósito. */
    public const RESEND_COOLDOWN_SECONDS = 60;

    /** Intentos fallidos permitidos antes de invalidar el código. */
    public const MAX_ATTEMPTS = 5;

    private const REASONS = [
        self::PURPOSE_LOGIN => 'iniciar sesión',
        self::PURPOSE_SENSITIVE => 'confirmar el cambio en tu cuenta',
    ];

    public static function isRequired(User $user): bool
    {
        return (bool) $user->two_factor_enabled;
    }

    /**
     * Segundos que faltan para poder pedir otro código (0 si ya se puede).
     */
    public static function secondsUntilResend(User $user, string $purpose): int
    {
        $last = VerificationCode::where('id_user', $user->id)
            ->where('purpose', $purpose)
            ->latest('id')
            ->first();

        if (!$last) {
            return 0;
        }

        $elapsed = $last->created_at->diffInSeconds(now(), true);

        return max(0, self::RESEND_COOLDOWN_SECONDS - (int) $elapsed);
    }

    /**
     * Genera un código nuevo, invalida los anteriores del mismo propósito y lo
     * envía al email actual del usuario.
     */
    public static function send(User $user, string $purpose): void
    {
        $code = (string) random_int(100000, 999999);

        VerificationCode::where('id_user', $user->id)
            ->where('purpose', $purpose)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        VerificationCode::create([
            'id_user' => $user->id,
            'purpose' => $purpose,
            'code_hash' => self::hash($code),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(self::EXPIRATION_MINUTES),
        ]);

        Mail::to($user->email)->send(new VerificationCodeMail(
            $user->name,
            $code,
            self::REASONS[$purpose] ?? 'verificar tu identidad',
            self::EXPIRATION_MINUTES
        ));
    }

    /**
     * Verifica el código. Si es correcto lo marca como usado (un solo uso).
     * Cada intento fallido suma un intento; al llegar al máximo el código se
     * invalida y hay que pedir uno nuevo.
     */
    public static function verify(User $user, string $purpose, ?string $code): bool
    {
        $code = trim((string) $code);

        if ($code === '') {
            return false;
        }

        $record = VerificationCode::where('id_user', $user->id)
            ->where('purpose', $purpose)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if (!$record) {
            return false;
        }

        if (!hash_equals($record->code_hash, self::hash($code))) {
            $record->attempts++;
            if ($record->attempts >= self::MAX_ATTEMPTS) {
                $record->used_at = now();
            }
            $record->save();

            return false;
        }

        $record->used_at = now();
        $record->save();

        return true;
    }

    /**
     * Paso de verificación reutilizable para los controllers.
     *
     * - Si no hace falta código (o ya vino uno válido) devuelve null y el
     *   controller sigue con la acción.
     * - Si no vino código, envía uno y devuelve la respuesta 200 con
     *   `requires_verification: true` (o 429 si se pidió hace muy poco).
     * - Si vino un código incorrecto o vencido, devuelve 422.
     *
     * @param  bool  $force  pedir código aunque el usuario tenga la verificación desactivada
     */
    public static function challenge(User $user, string $purpose, ?string $code, array $audith, bool $force = false): ?JsonResponse
    {
        if (!$force && !self::isRequired($user)) {
            return null;
        }

        if ($code === null || trim($code) === '') {
            $wait = self::secondsUntilResend($user, $purpose);
            if ($wait > 0) {
                return ApiResponse::create(
                    "Esperá {$wait} segundos para pedir otro código",
                    429,
                    ['code' => ["Esperá {$wait} segundos para pedir otro código"], 'resend_in' => $wait],
                    $audith
                );
            }

            self::send($user, $purpose);

            return ApiResponse::create(
                'Te enviamos un código de verificación a tu email',
                200,
                self::pendingPayload($user),
                $audith
            );
        }

        if (!self::verify($user, $purpose, $code)) {
            return ApiResponse::create(
                'Código de verificación incorrecto o vencido',
                422,
                ['code' => ['El código es incorrecto o venció. Pedí uno nuevo si es necesario.']],
                $audith
            );
        }

        return null;
    }

    /**
     * Email parcialmente oculto para mostrar en el front: ju***@gmail.com
     */
    public static function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
        $visible = mb_substr($local, 0, min(2, max(1, mb_strlen($local) - 1)));

        return $visible . '***' . ($domain !== '' ? '@' . $domain : '');
    }

    /**
     * Datos que recibe el front cuando tiene que pedir el código.
     */
    public static function pendingPayload(User $user): array
    {
        return [
            'requires_verification' => true,
            'email' => self::maskEmail($user->email),
            'expires_in' => self::EXPIRATION_MINUTES * 60,
            'resend_in' => self::RESEND_COOLDOWN_SECONDS,
        ];
    }

    private static function hash(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }
}
