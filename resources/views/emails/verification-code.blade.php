@component('mail::message')
# Código de verificación

Hola {{ $name }},

Usá este código para {{ $reason }}:

@component('mail::panel')
<div style="font-size: 28px; font-weight: bold; letter-spacing: 8px; text-align: center;">{{ $code }}</div>
@endcomponent

El código vence en {{ $minutes }} minutos y solo se puede usar una vez.

Si no fuiste vos, ignorá este correo y te recomendamos cambiar tu contraseña.

Gracias,<br>
El equipo de Galpón Pueyrredón
@endcomponent
