# Fecha/hora de inicio del evento en la ficha logística (`event_start_datetime`)

Guía para el frontend de la ficha logística. Contrato completo del endpoint
en [ficha-logistica.md](ficha-logistica.md).

## Qué cambia

Antes, la fecha/hora de inicio del evento se mostraba **solo lectura**,
armada con `budget.date_event` + `budget.time_event`.

Ahora es un **campo editable** de la ficha: `event_start_datetime`.

- Viene **precargado** con la fecha/hora del presupuesto
  (`date_event` + `time_event`).
- El cliente lo puede **editar**. Lo que cargue se guarda en la ficha y **no
  modifica el presupuesto**.
- Mientras el cliente no lo edite, si un admin cambia la fecha del
  presupuesto, la ficha muestra la fecha nueva sola.

## Leer el valor

`GET /api/v1/logistics-sheet/{token}` (y el endpoint admin
`GET /api/logistics-sheet/budget/{id}`) devuelven en `logistics_sheet`:

| Campo | Tipo | Qué es |
|---|---|---|
| `event_start_datetime` | string ISO 8601 (UTC) \| `null` | Fecha/hora de inicio a mostrar en el input. Si el cliente no la editó, es la del presupuesto. Solo es `null` si el presupuesto no tiene `date_event` |
| `event_start_datetime_edited` | boolean | `true` si el valor es uno que cargó el cliente; `false` si es el del presupuesto |

Ejemplo (el presupuesto es del 20/11/2026 a las 18:00, el cliente no lo editó):

```json
{
  "data": {
    "logistics_sheet": {
      "event_start_datetime": "2026-11-20T21:00:00.000000Z",
      "event_start_datetime_edited": false,
      "event_end_datetime": null
    },
    "budget": {
      "date_event": "2026-11-20",
      "time_event": "18:00:00"
    }
  }
}
```

> **Ojo con la zona horaria:** `event_start_datetime` viene en **UTC** (sufijo
> `Z`), igual que `event_end_datetime`. 18:00 de Argentina = `21:00Z`.
> Convertirlo a hora local antes de mostrarlo en el input, por ejemplo con
> `new Date(value)` / dayjs / date-fns. **No** cortar el string a mano.

Usá siempre `logistics_sheet.event_start_datetime` para el input, no
`budget.date_event`/`time_event`: el backend ya resuelve cuál de los dos
corresponde.

## Guardar un valor editado

`POST /api/v1/logistics-sheet/{token}`, como cualquier otro campo de la ficha
(carga incremental: si no mandás la key, no se toca).

```json
{ "event_start_datetime": "2026-11-20 19:30:00" }
```

- Formato: fecha/hora **en hora de Argentina**, sin zona,
  `YYYY-MM-DD HH:mm:ss`. Es el mismo formato que `event_end_datetime`.
- Respuesta: la ficha actualizada, con `event_start_datetime_edited: true`.

## Volver al valor del presupuesto

Mandar la key con `null`:

```json
{ "event_start_datetime": null }
```

Se borra el valor editado y la ficha vuelve a mostrar la fecha/hora del
presupuesto (`event_start_datetime_edited: false`). Sirve para un botón tipo
"Usar la fecha del presupuesto", que conviene mostrar solo cuando
`event_start_datetime_edited` es `true`.

## `field_status`

`event_start_datetime` ahora es un nombre válido en `field_status`
(`later` / `not_applicable`), como el resto de los campos:

```json
{ "field_status": [{ "field": "event_start_datetime", "status": "later" }] }
```

## Completitud

Es un campo **mandatorio**, pero como siempre viene precargado con la fecha
del presupuesto, en la práctica ya cuenta como completo sin que el cliente
haga nada. La excepción es marcarlo como `later`: en ese caso la ficha no
queda completa hasta que se saque la marca.

## Checklist para el front

- [ ] Reemplazar el texto solo lectura de "Inicio del evento" por un input
      datetime editable.
- [ ] Precargar el input con `logistics_sheet.event_start_datetime`,
      convertido de UTC a hora local.
- [ ] Al guardar, mandar `event_start_datetime` en formato
      `YYYY-MM-DD HH:mm:ss` (hora de Argentina).
- [ ] Opcional: si `event_start_datetime_edited` es `true`, mostrar
      "Usar fecha del presupuesto" (manda `null`).
- [ ] Deshabilitar el input si `read_only` es `true`, como el resto de la
      ficha.
