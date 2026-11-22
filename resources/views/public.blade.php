<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#000000">
        <title inertia>Ascanio Publishing</title>
        <link rel="icon" type="image/png" href="/assets/ascanio/favicon.png">
        @vite(['resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="public-body">
        @inertia
    </body>
</html>
