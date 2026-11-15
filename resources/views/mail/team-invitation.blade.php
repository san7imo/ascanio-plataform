<!doctype html>
<html lang="es">
<body>
    <h1>Invitación a {{ $workspaceName }}</h1>
    <p>Te invitaron a colaborar en este espacio de Ascanio. Abre el enlace e inicia sesión o crea una cuenta con este mismo correo y verifícalo.</p>
    <p><a href="{{ $claimUrl }}">Revisar invitación</a></p>
    <p>Vigente hasta {{ $expiresAt }} (UTC). Revisa los roles y alcances antes de aceptar.</p>
    <p>Este enlace no inicia sesión ni otorga propiedad musical o facultad para firmar por otras personas.</p>
</body>
</html>
