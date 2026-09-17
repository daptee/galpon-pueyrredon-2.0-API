# Cómo quitar/vaciar cada campo de la ficha logística

Guía de referencia para el frontend: qué mandar en `POST /api/v1/logistics-sheet/{token}`
para borrar/vaciar un campo que el cliente ya había cargado. Contrato
completo del endpoint en [ficha-logistica.md](ficha-logistica.md).

Regla general: **una key ausente no toca el campo** (se mantiene lo que
había); **una key presente es la que borra o cambia el valor** — la forma de
"presente" varía según el tipo de campo, ver tabla.

## Campos de texto / fecha / número / array simple

Mandar la key con valor **`null`**.

| Campo | Qué pasa al mandar `null` |
|---|---|
| `event_type_other` | Se vacía el texto libre del tipo de evento |
| `event_end_datetime` | Se vacía la fecha/hora de fin |
| `address_maps_link` | Se vacía el link de Maps |
| `accessibility_comments` | Se vacía el comentario de accesibilidad |
| `delivery_windows` | Se vacían las ventanas de entrega (las 3 opciones) |
| `pickup_windows` | Se vacían las ventanas de retiro (las 3 opciones) |
| `cushion_color` | Se vacía el color de almohadones |
| `insurance_required` | Se vacía (vuelve a "sin definir") |
| `insurance_request_text` | Se vacía el texto de solicitud de seguros |
| `additional_requirements` | Se vacía |
| `completion_percentage` | Se vacía el % de avance |
| `order_contact_name` / `order_contact_phone` | Se vacía el contacto del pedido (vive en `budget_delivery_data`) |
| `reception_contact_name` / `reception_contact_phone` | Se vacía el contacto de recepción (ídem) |
| `additional_order_details` | Se vacía (ídem) |
| `delivery_options` | Se vacía la dirección/nota de retiro alternativa (ídem) |
| `id_event_type` | Se vacía el tipo de evento del combo (ídem; el cliente puede pasar a usar `event_type_other` en su lugar) |

Ejemplo:
```json
{ "cushion_color": null, "order_contact_name": null }
```
Vacía esos dos campos puntuales; el resto de la ficha queda intacto.

## `budget_ratified`

Es un booleano `NOT NULL` en la base — no existe un estado "vacío", solo
`true`/`false`. Para "sacar" la ratificación, mandar:
```json
{ "budget_ratified": false }
```
(Mandar `null` acá da **422**, no lo intentes.)

## `field_status`

- Para borrar **todas** las marcas (`later`/`not_applicable`) de una sola
  vez: mandar `field_status: []` (array vacío). `null` da **422**, tiene
  que ser un array.
- Para borrar la marca de **un solo campo**, mandar ese campo con
  `status: "completed"` (o cualquier valor que no sea `later`/`not_applicable`):
  ```json
  { "field_status": [{ "field": "cushion_color", "status": "completed" }] }
  ```
  Esto solo saca la marca; si además querés vaciar el valor real de ese
  campo, hay que mandarlo en `null` aparte (ver tabla de arriba) — son dos
  cosas independientes (el valor y la marca).

## Archivos: `insurance_document`, `assembly_plan_document`, `insurance_additional_documents`

Los archivos no se "vacían" mandando `null` (no tiene sentido en
`multipart/form-data`, y además reemplazar por `null` podría confundirse
con "no toqué este campo"). Se usan **flags de borrado** dedicadas:

| Para quitar | Mandar |
|---|---|
| El documento de seguro principal (`insurance_document_path`) | `remove_insurance_document: true` |
| El plano de armado (`assembly_plan_path`) | `remove_assembly_plan_document: true` |
| Uno o varios documentos adicionales de seguro | `remove_insurance_additional_documents: ["<path>", "<path2>"]` — los `path` son los que devuelve la API en `insurance_additional_documents[].path` |

En los tres casos, el archivo también se borra físicamente del servidor
(no queda ocupando espacio).

Ejemplo (raw JSON, sin subir nada nuevo en el mismo request):
```json
{
  "remove_insurance_document": true,
  "remove_insurance_additional_documents": [
    "storage/logistics_sheets/12/1758112233_65abc1_art.pdf"
  ]
}
```

**Se puede combinar quitar y subir en el mismo request**: por ejemplo,
mandar `remove_insurance_document: true` junto con un archivo nuevo en
`insurance_document` — primero se borra el viejo y después se guarda el
nuevo. De hecho, si simplemente subís un archivo nuevo en
`insurance_document` sin pedir el borrado, el archivo anterior se borra
solo (ya no quedan archivos huérfanos en el servidor).

## Campos que **no** se pueden vaciar/quitar

- `event_start_datetime` y `address` (la dirección del evento): no son
  campos editables de la ficha, se leen en vivo del presupuesto — no hay
  nada que "quitar" del lado de la ficha.
- `insurance_document_path`/`assembly_plan_path`/`insurance_additional_documents`
  como texto/JSON directo: no se pueden tocar mandando esas columnas
  directamente en el body, solo a través de las flags `remove_*` de arriba.
