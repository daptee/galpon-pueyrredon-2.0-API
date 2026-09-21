# Recordatorios automáticos de la Ficha Logística (cron)

Endpoint pensado para dejarlo corriendo en un cron (una vez por día). Revisa
los presupuestos aprobados según qué tan cerca está su evento y, si la ficha
logística todavía no está completa, le reenvía el link al cliente por mail
— con un encabezado más urgente cuanto más cerca está el evento.

## Endpoint

```
GET /api/logistics-sheet/send-reminders
```

**Sin JWT/login** (a propósito: lo llama un cron, no un usuario logueado).
No modifica nada por GET salvo el propio estado de recordatorios ya
enviados — es seguro llamarlo repetidas veces, no duplica envíos (ver
"Idempotencia" más abajo).

### Protegerlo con un secret (opcional pero recomendado)

Si configurás `LOGISTICS_SHEET_REMINDERS_SECRET` en el `.env`, el endpoint
exige que lo mandes como query param:

```
GET /api/logistics-sheet/send-reminders?secret=TU_SECRET
```

Si no está configurado, el endpoint queda abierto (mismo criterio que ya
usan otros endpoints de mantenimiento del proyecto, como `/backup`). Se
recomienda configurarlo, ya que este sí manda mails a clientes reales.

## Cómo dejarlo en un cron

Cualquier mecanismo que pueda pegarle a una URL una vez por día sirve. Por
ejemplo, un crontab de Linux:

```
0 9 * * * curl -s "https://tu-dominio.com/api/logistics-sheet/send-reminders?secret=TU_SECRET" >> /var/log/logistics-reminders.log 2>&1
```

(a las 9am todos los días). Si el hosting tiene un panel de cron jobs
(cPanel, etc.), se configura ahí apuntando a esa misma URL. Tiene que correr
**todos los días** (no solo en fechas puntuales), porque es el propio
endpoint el que decide, cada vez que corre, a quién le toca recordatorio hoy.

## Cadencia (la que pidió el documento original)

| Cuándo | Qué manda |
|---|---|
| Presupuesto exactamente a **15, 10 o 7 días** del evento | Recordatorio de presentación (mismo encabezado los 3, solo cambia el número de días en el texto) |
| Presupuesto a **5, 4, 3, 2, 1 o 0 días** del evento | Reclamo, **todos los días** (mismo encabezado urgente, "último reclamo") |

En ambos casos, **solo si la ficha logística todavía no está completa** —
apenas se completa, dejan de mandarse recordatorios (ver punto 3 más abajo).
El día 0 es el día del evento; no se manda nada después de esa fecha.

Esto reemplaza la versión anterior de este endpoint, que solo revisaba
7/3/1 días — ahora es la cadencia completa: `MILESTONE_REMINDER_DAYS = [15, 10, 7]`
y `DAILY_REMINDER_FROM_DAYS = 5` en
[`LogisticsSheetController`](../app/Http/Controllers/LogisticsSheetController.php).

## Qué hace exactamente (por cada uno de esos días)

1. Busca presupuestos con `id_budget_status = 3` (Aprobado), `date_event`
   exactamente igual a *hoy + N días*, y que tengan `client_mail` cargado.
2. Para cada uno, obtiene la ficha logística (la crea con un token nuevo si
   todavía no existía — igual que el endpoint admin de "obtener/crear ficha").
3. Si la ficha ya está **completa** (`is_completed = true`) → no manda nada,
   se registra como `skipped` con motivo `ficha_completa`. Esto corta la
   cadena de recordatorios apenas el cliente termina de completarla, sea
   cual sea el día en el que la complete.
4. Si ya se mandó el recordatorio de **ese mismo N de días** antes (se
   guarda en `logistics_sheet.reminder_days_sent`) → tampoco manda de nuevo,
   se registra como `skipped` con motivo `ya_enviado`.
5. Si no fue ni completada ni recordada para ese N, manda el mail y agrega
   ese N a `reminder_days_sent`.

## Idempotencia

Se puede llamar más de una vez el mismo día sin riesgo de mandar el mismo
recordatorio duplicado — la segunda vez, para los mismos presupuestos, va a
salir como `skipped: ya_enviado`. Esto es importante porque significa que no
hace falta preocuparse por reintentos del cron ni por llamarlo manualmente
para probar sin miedo a espamear al cliente dos veces.

## Los 2 encabezados

Igual que pedía el documento original de la ficha logística, el mail
cambia de tono según la etapa:

| Etapa | Asunto | Tono |
|---|---|---|
| 15, 10 o 7 días antes | "Recordatorio: completá la ficha logística de tu evento (faltan N días)" | Recordatorio de presentación, mismo tono los 3 |
| 5 días antes en adelante, todos los días | "Último reclamo: todavía falta completar la ficha logística de tu evento" | Urgente, insistente |

Se define en [`App\Mail\LogisticsSheetReminder`](../app/Mail/LogisticsSheetReminder.php)
y la plantilla en [`emails/logistics-sheet/reminder.blade.php`](../resources/views/emails/logistics-sheet/reminder.blade.php).

## Respuesta

```json
{
  "message": "Recordatorios procesados",
  "data": {
    "sent": [
      { "id_budget": 13007, "days_remaining": 7, "mail_sent_to": "cliente@correo.com" }
    ],
    "skipped": [
      { "id_budget": 13010, "days_remaining": 3, "reason": "ficha_completa" },
      { "id_budget": 13011, "days_remaining": 1, "reason": "ya_enviado" }
    ],
    "errors": []
  }
}
```

Si falla el envío de un presupuesto puntual (por ejemplo, un problema
puntual de mail), no frena a los demás — queda registrado en `errors` con
el `id_budget` y el motivo, y sigue procesando el resto.

## Fuera de alcance de este endpoint (por si hace falta más adelante)

El documento original de la ficha logística también pedía un **primer**
mail (con su propio "encabezado de presentación") disparado automáticamente
**al aprobar el presupuesto**. Eso no está incluido acá — hoy ese primer
envío se sigue haciendo a mano, llamando al endpoint admin
`GET /logistics-sheet/budget/{id}` (ver [ficha-logistica.md](ficha-logistica.md)).
Si más adelante se quiere automatizar también ese disparo, hay que
engancharlo en el flujo de aprobación de `BudgetController` (cuando
`id_budget_status` pasa a 3).
