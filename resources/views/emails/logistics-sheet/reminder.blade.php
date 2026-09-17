@component('mail::message')
Hola,

{{ $intro }}

@component('mail::button', ['url' => $publicUrl])
Completar ficha logística
@endcomponent

Si el botón no funciona, copiá y pegá este link en tu navegador:
{{ $publicUrl }}

Saludos,
**Galpón Pueyrredón**
@endcomponent
