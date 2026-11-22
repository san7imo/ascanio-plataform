<!doctype html>
<html lang="es">
<body>
    <h1>Documento compartido desde {{ $workspaceName }}</h1>
    <p>Abre el enlace e inicia sesión o crea una cuenta con este mismo correo y verifícalo.</p>
    <p><a href="{{ $claimUrl }}">Revisar invitación al documento</a></p>
    <p>El acceso vence el {{ $expiresAt }} (UTC), incluso si aceptas más tarde.</p>
    <p>Revisarás la versión y el acceso antes de aceptar. La invitación no te incorpora al equipo ni concede derechos musicales o facultad para firmar.</p>
</body>
</html>
