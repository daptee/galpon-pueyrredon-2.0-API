# Recordatorios automáticos de la Ficha Logística (cron)

Endpoint pensado para dejarlo corriendo en un cron (una vez por día). Revisa
los presupuestos aprobados cuyo evento es exactamente dentro de **7, 3 o 1
día**, y si la ficha logística todavía no está completa, le reenvía el link
al cliente por mail — con un encabezado más urgente cuanto más cerca está el
evento.

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
(cPanel, etc.), se configura ahí apuntando a esa misma URL.

## Qué hace exactamente

Para cada uno de los 3 "días antes" (7, 3, 1):

1. Busca presupuestos con `id_budget_status = 3` (Aprobado), `date_event`
   exactamente igual a *hoy + N días*, y que tengan `client_mail` cargado.
2. Para cada uno, obtiene la ficha logística (la crea con un token nuevo si
   todavía no existía — igual que el endpoint admin de "obtener/crear ficha").
3. Si la ficha ya está **completa** (`is_completed = true`) → no manda nada,
   se registra como `skipped` con motivo `ficha_completa`.
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

## Los 3 encabezados

Igual que pedía el documento original de la ficha logística, el mail cambia
de tono según la proximidad:

| Días antes | Asunto | Tono |
|---|---|---|
| 7 | "Recordatorio: completá la ficha logística de tu evento" | Recordatorio amable, primera vez |
| 3 | "¡Faltan 3 días! Todavía necesitamos los datos logísticos de tu evento" | Más urgente |
| 1 | "Último aviso: mañana es tu evento y falta completar la ficha logística" | Último llamado |

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

El documento original de la ficha logística pedía una cadencia más completa
(mail al aprobar el presupuesto, después 15/10/7 días antes, y diario desde
los 5 días antes hasta completarla). Lo que se armó acá es específicamente
lo que se pidió ahora: **7, 3 y 1 día antes**, sin el envío al aprobar ni el
diario desde los 5 días. Si más adelante hace falta esa cadencia completa,
es extender `LogisticsSheetController::REMINDER_DAYS` (y, para el envío al
aprobar, enganchar la llamada a `getOrCreate`/el envío inicial en el flujo
de aprobación de `BudgetController`).
