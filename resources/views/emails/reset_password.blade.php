<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restablecimiento de Contraseña</title>
</head>
<body>
    <p>Hola {{ $user->name }},</p>
    <p>Tu nueva contraseña es: <strong>{{ $password }}</strong></p>
    <p>Por seguridad, te recomendamos cambiarla lo antes posible desde la configuración de tu cuenta.</p>
    <p>Gracias,</p>
    <p>El equipo de Galpon pueyrredon</p>
</body>
</html>
