# Contactos del cliente y localidad en la ficha logística

Guía para el frontend de la ficha logística. Contrato completo del endpoint
en [ficha-logistica.md](ficha-logistica.md).

`GET /api/v1/logistics-sheet/{token}` ahora devuelve dos datos más dentro de
`data.logistics_sheet.budget`:

- `client.contacts`: los contactos del cliente, para el selector de contacto
  del pedido y de recepción.
- `place.locality`: la localidad del lugar del evento.

## Ejemplo de respuesta

Solo se muestran las partes nuevas:

```json
{
  "code": 1,
  "response": "ok",
  "data": {
    "logistics_sheet": {
      "budget": {
        "client": {
          "id": 201,
          "name": "Empresa Ejemplo",
          "contacts": [
            { "id": 367, "id_client": 201, "name": "Clara", "lastname": "Candia", "phone": "1123676663" },
            { "id": 368, "id_client": 201, "name": "Juan", "lastname": null, "phone": "11-2222-3333" }
          ]
        },
        "place": {
          "id": 91,
          "name": "La Quinta de Bella Vista",
          "address": "Quirno 2700",
          "locality": { "id": 155, "locality": "Bella Vista" }
        }
      }
    }
  }
}
```

## `budget.client.contacts`

Array con los contactos del cliente del presupuesto.

| Campo | Tipo | Uso |
|---|---|---|
| `id` | int | Identifica cada contacto en el selector |
| `name` | string | Nombre |
| `lastname` | string \| `null` | Apellido. Puede venir vacío o `null`: mostrar solo el nombre en ese caso |
| `phone` | string \| `null` | Celular. Se completa solo al elegir el contacto |
| `id_client` | int | Uso interno del backend, se puede ignorar |

- **Orden:** el mismo que en `GET /api/event/{id}` (admin). El **primer**
  contacto es el que se precarga en el contacto del pedido y en el de
  recepción.
- Si el cliente no tiene contactos, viene `[]`.
- Si el presupuesto no tiene cliente asociado, `budget.client` es `null`:
  controlarlo antes de leer `contacts`.
- Al elegir un contacto, el front manda los datos como siempre
  (`order_contact_name` / `order_contact_phone`,
  `reception_contact_name` / `reception_contact_phone`). El backend no guarda
  el `id` del contacto elegido, solo el nombre y el teléfono.

## `budget.place.locality`

| Campo | Tipo | Uso |
|---|---|---|
| `id` | int | Id de la localidad |
| `locality` | string | Nombre a mostrar (ej. "CABA") |

- Es solo lectura, como la dirección (`budget.place.address`): se toma del
  lugar del presupuesto.
- Puede venir `null` si el lugar no tiene localidad cargada, y `budget.place`
  también puede ser `null` si el presupuesto no tiene lugar.

## Checklist para el front

- [ ] Llenar el selector de contactos con `budget.client.contacts`, mostrando
      "Nombre Apellido" (o solo el nombre si `lastname` viene vacío).
- [ ] Precargar el contacto del pedido y el de recepción con el primer
      contacto de la lista, si hay alguno.
- [ ] Al elegir un contacto, completar el teléfono con su `phone`.
- [ ] Mostrar `budget.place.locality.locality` junto a la dirección del
      evento, como solo lectura.
- [ ] Contemplar `client`, `place` o `locality` en `null` y `contacts` vacío.
