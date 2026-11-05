# Verificación por código (email)

Al iniciar sesión y al hacer acciones sensibles se pide un código de 6 dígitos
que llega al email del usuario. Viene **activada por defecto** y cada usuario
la puede desactivar desde **Configuración > Seguridad**.

Requiere correr `database/sql/two_factor_codes.sql`.

## Cómo funciona (igual en todos los endpoints)

Se llama dos veces al mismo endpoint con el mismo body:

1. **Sin `code`** → si el usuario tiene la verificación activa, el backend
   envía el código y responde `200`:

   ```json
   {
     "message": "Te enviamos un código de verificación a tu email",
     "data": {
       "requires_verification": true,
       "email": "ju***@gmail.com",
       "expires_in": 600,
       "resend_in": 60
     }
   }
   ```

   El front muestra el input del código. Si el usuario tiene la verificación
   desactivada, la acción se hace directo y la respuesta es la de siempre.

2. **Con `code`** (mismo body + `"code": "123456"`) → si es correcto se
   ejecuta la acción y la respuesta es la normal del endpoint.

**Siempre chequear `data.requires_verification === true`** para saber si hay
que mostrar el input del código.

### Errores

| Código | Cuándo | `error` |
|---|---|---|
| `422` | Código incorrecto, vencido o ya usado | `{ "code": ["El código es incorrecto o venció..."] }` |
| `429` | Se pidió reenviar antes de 60 s | `{ "code": ["Esperá N segundos..."], "resend_in": N }` |

- **Reenviar código:** volver a llamar al endpoint **sin** `code`. El código
  anterior deja de servir.
- El código vence a los **10 minutos**, es de un solo uso y después de
  **5 intentos fallidos** se invalida (hay que pedir otro).

## Endpoints

### `POST /api/auth/login`

```json
{ "login": "usuario o email", "password": "...", "code": "123456" }
```

El token solo se devuelve después de validar el código. Las credenciales se
vuelven a validar en el segundo llamado, así que hay que mandar `login` y
`password` de nuevo.

### `POST /api/auth/change-password`

```json
{
  "email": "...",
  "current_password": "...",
  "new_password": "...",
  "new_password_confirmation": "...",
  "code": "123456"
}
```

### `PUT /api/user/own`

Solo pide código si cambia el **email** (distinto del actual) o la
**contraseña**. Cambiar nombre, teléfono, tema, etc. no pide código.
El código se envía al **email actual**, no al nuevo.

### `PUT /api/user/own/two-factor` (nuevo) — Configuración > Seguridad

```json
{ "enabled": false, "code": "123456" }
```

- **Desactivar** (`enabled: false`) pide código (mismo flujo de dos pasos).
- **Activar** (`enabled: true`) no pide código.
- Responde el usuario actualizado.

## Estado actual

El usuario que devuelve el login (y `PUT /api/user/own`) trae
`two_factor_enabled: true | false` para mostrar el switch en Seguridad.
