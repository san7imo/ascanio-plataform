@php
    $status = trim($__env->yieldContent('code', '500'));
    [$title, $message] = match ($status) {
        '401' => ['Inicia sesión', 'Necesitas iniciar sesión para continuar.'],
        '403' => ['Acceso no autorizado', 'No tienes acceso a esta operación.'],
        '404' => ['Página no disponible', 'La dirección solicitada no está disponible.'],
        '419' => ['La sesión ha vencido', 'Vuelve a abrir el formulario e inténtalo de nuevo.'],
        '429' => ['Demasiados intentos', 'Espera un momento antes de volver a intentarlo.'],
        '503' => ['Servicio temporalmente no disponible', 'Inténtalo de nuevo más tarde.'],
        default => ['No se pudo completar la operación', 'Inténtalo de nuevo más tarde.'],
    };
@endphp
<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title }} · {{ config('app.name') }}</title>
        <style>
            :root { color-scheme: light dark; font-family: system-ui, sans-serif; }
            body { margin: 0; min-height: 100dvh; display: grid; place-items: center; }
            main { max-width: 32rem; padding: 2rem; }
            h1 { font-size: 1.5rem; }
            p { line-height: 1.6; }
            a { color: inherit; text-underline-offset: .25em; }
        </style>
    </head>
    <body>
        <main>
            <p>{{ $status }}</p>
            <h1>{{ $title }}</h1>
            <p>{{ $message }}</p>
            <a href="{{ url('/') }}">Volver al inicio</a>
        </main>
    </body>
</html>
