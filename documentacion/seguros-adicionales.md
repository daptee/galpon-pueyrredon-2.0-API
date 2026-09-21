# Seguros: texto alternativo y documentos adicionales

Dos campos nuevos en la sección "Requerimientos" de la ficha logística
(`POST /api/v1/logistics-sheet/{token}`). Contrato completo del endpoint en
[ficha-logistica.md](ficha-logistica.md).

## 1. `insurance_request_text` — alternativa en texto a `insurance_document`

Hasta ahora, si `insurance_required = "yes"`, la única forma de resolver el
requisito era subiendo un archivo (`insurance_document`). Ahora hay una
segunda opción: un campo de texto libre para cuando el cliente todavía no
tiene el archivo pero puede describir la cobertura (o avisar que lo va a
mandar por otro medio).

| Campo | Tipo |
|---|---|
| `insurance_request_text` | string, máx. 1000 caracteres |

**Son alternativas entre sí**: alcanza con mandar **uno de los dos**
(`insurance_document` o `insurance_request_text`) para que el requisito se
dé por resuelto. No hace falta mandar ambos, y no hay ningún orden de
prioridad — el que se haya cargado más recientemente es el que vale a
efectos de mostrarlo, pero para el cálculo de "ficha completa" (`is_completed`)
alcanza con que exista al menos uno de los dos.

Ejemplo (raw JSON, sin adjuntos):
```json
{
  "insurance_required": "yes",
  "insurance_request_text": "Cobertura ART Nro 12345 vigente hasta 12/2026, la enviamos por mail aparte."
}
```

## 2. `insurance_additional_documents[]` — documentos de seguro extra

Además del documento principal, ahora se pueden sumar documentos de seguro
adicionales (por ejemplo: ART, seguro de vehículos, u otro adicional que
pida el lugar del evento).

- Input de **archivos múltiple**: `insurance_additional_documents[]`
  (repetir la key por cada archivo en el `multipart/form-data`).
- Máximo **5 archivos por request**, 10MB cada uno.
- **Es acumulativo**: cada guardado suma los archivos nuevos a los que ya
  había cargados — nunca los reemplaza. Si el cliente vuelve a guardar la
  ficha sin mandar este campo, los documentos ya subidos quedan intactos.
- **No hay forma de borrar uno individual** desde este endpoint por ahora.

Ejemplo (multipart/form-data):
```
insurance_additional_documents[] = art-constructora.pdf
insurance_additional_documents[] = seguro-vehiculo.pdf
```

### Cómo viene en las respuestas (`GET` y `POST`)

Se agregó a `logistics_sheet` como un array de objetos `{path, original_name}`:

```json
{
  "insurance_required": "yes",
  "insurance_document_path": "storage/logistics_sheets/12/167_poliza.pdf",
  "insurance_request_text": null,
  "insurance_additional_documents": [
    { "path": "storage/logistics_sheets/12/168_art.pdf", "original_name": "ART Constructora.pdf" },
    { "path": "storage/logistics_sheets/12/169_rc-auto.pdf", "original_name": "RC Auto.pdf" }
  ]
}
```

`path` es relativo (igual que `insurance_document_path`/`assembly_plan_path`
de siempre): hay que prefijarlo con la base del backend para armar la URL
completa. `original_name` es el nombre de archivo tal como lo subió el
cliente, útil para mostrarlo en la UI en vez de un nombre técnico.

## `field_status`

`insurance_document` (el nombre de grupo que ya existía en `field_status`)
ahora cubre **tanto** el archivo como el texto: si el cliente marca ese
campo como `later`/`not_applicable`, y después manda valor para
`insurance_document` **o** `insurance_request_text`, la marca se limpia
sola. `insurance_additional_documents` no tiene entrada en `field_status`
(es opcional y no participa del cálculo de completitud).
