@component('mail::message')
# Nuevo mensaje de contacto desde la web

**Nombre:** {{ $name }} {{ $lastName }}

**Email:** {{ $email }}

**Teléfono:** {{ $phone }}

**Comentarios:**
{{ $comments }}

---
Este mensaje fue enviado desde el formulario de contacto de la web.
@endcomponent
