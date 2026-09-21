@component('mail::message')
Hola, gracias por confiar en **Galpón Pueyrredón** para tu evento.

Para poder organizar la logística de entrega y retiro (Presupuesto Nro
{{ $budget->id }}), necesitamos que completes algunos datos: dirección,
horarios de armado y desarme, contactos en el lugar y, si corresponde,
seguros y planos.

Podés completarlo de a poco, no hace falta terminarlo en una sola vez.

@component('mail::button', ['url' => $publicUrl])
Completar ficha logística
@endcomponent

Si el botón no funciona, copiá y pegá este link en tu navegador:
{{ $publicUrl }}

Saludos,
**Galpón Pueyrredón**
@endcomponent
