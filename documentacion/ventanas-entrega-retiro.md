# Opciones de entrega y retiro (hasta 3 ventanas Desde/Hasta)

Cómo mandar la pantalla de "Armado y desarme" (opciones de entrega y de
retiro, hasta 3 rangos de fecha/hora cada una) al backend.

## Importante: no es lo mismo que `budget_delivery_data`

- `budget_delivery_data.delivery_datetime` / `.widthdrawal_datetime` — son
  campos de texto libre, legacy, pensados para el panel admin. Ahí **no
  entran 3 rangos estructurados**, solo un texto.
- `logistics_sheets.delivery_windows` / `.pickup_windows` — es lo que usa
  esta pantalla: un **array JSON de hasta 3 objetos**
  `{datetime_from, datetime_to}`. Se manda al endpoint público de la ficha
  logística, **no** al de `budget-delivery-data`.

El backend, además, arma automáticamente un resumen en texto de la
**primera opción** (la obligatoria) y lo espeja en
`budget_delivery_data.delivery_datetime`/`.widthdrawal_datetime` para que lo
vea el panel admin — pero eso es interno; el frontend de la ficha nunca
tiene que tocar `budget_delivery_data` directamente (ver
[budget-delivery-data-admin.md](budget-delivery-data-admin.md) si hace falta
más detalle de esa parte). Esas dos columnas son texto libre pensado para
una sola ventana, así que aunque el cliente cargue 2 o 3 opciones, solo la
primera se refleja ahí — las 3 completas solo quedan en
`logistics_sheets.delivery_windows`/`pickup_windows`.

## Endpoint

```
POST /api/v1/logistics-sheet/{token}
```

Sin autenticación (es el link que recibe el cliente). Ver el resto del
contrato de este endpoint (carga incremental, `field_status`, plazo de
edición, etc.) en [ficha-logistica.md](ficha-logistica.md).

## Body (JSON)

```json
{
  "delivery_windows": [
    { "datetime_from": "2026-11-19 09:00:00", "datetime_to": "2026-11-19 12:00:00" },
    { "datetime_from": "2026-11-19 14:00:00", "datetime_to": "2026-11-19 17:00:00" }
  ],
  "pickup_windows": [
    { "datetime_from": "2026-11-21 09:00:00", "datetime_to": "2026-11-21 12:00:00" }
  ]
}
```

Reglas:

- Cada array acepta hasta 3 objetos. Solo el primero de cada uno es
  obligatorio; el 2do y 3ro son las opciones extra, opcionales (el botón
  "X" del mockup es para sacar esa opción del array antes de mandarlo).
- `datetime_from`/`datetime_to` van con fecha **y** hora juntas en el mismo
  valor (no separadas en dos inputs distintos del lado del backend).
- `datetime_to` tiene que ser igual o posterior a `datetime_from`; si no,
  el backend devuelve `422`.
- Es carga incremental: si en un guardado solo se manda `delivery_windows`
  (sin `pickup_windows`), lo que ya estaba guardado en `pickup_windows` no
  se toca.

## Body (multipart/form-data)

Si ese mismo guardado va a incluir además algún adjunto (póliza de seguro o
plano), hay que mandar todo como `multipart/form-data`, y los arrays se
mandan con notación de corchetes:

```
delivery_windows[0][datetime_from] = 2026-11-19 09:00:00
delivery_windows[0][datetime_to]   = 2026-11-19 12:00:00
delivery_windows[1][datetime_from] = 2026-11-19 14:00:00
delivery_windows[1][datetime_to]   = 2026-11-19 17:00:00
pickup_windows[0][datetime_from]   = 2026-11-21 09:00:00
pickup_windows[0][datetime_to]     = 2026-11-21 12:00:00
```

Ejemplos completos armados (incluyendo con adjuntos) en
[postman/Logistics Sheet.postman_collection.json](../postman/Logistics%20Sheet.postman_collection.json),
ítem "Guardar armado/desarme y seguros".

## Cómo vuelve en el `GET` (precarga)

`GET /api/v1/logistics-sheet/{token}` devuelve `logistics_sheet.delivery_windows`
y `logistics_sheet.pickup_windows` con la misma forma (array de
`{datetime_from, datetime_to}`), listos para precargar la pantalla tal cual
se ve en el mockup.
