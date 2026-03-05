<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Solicitud de Eliminacion de Datos - ChatMe</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="font-sans antialiased bg-white dark:bg-gray-900">
    <header class="px-6 py-4 flex items-center justify-between max-w-4xl mx-auto">
        <a href="{{ route('landing') }}" class="text-xl font-bold text-gray-900 dark:text-white">ChatMe</a>
    </header>

    <main class="max-w-4xl mx-auto px-6 py-8">
        <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-2">Solicitud de Eliminacion de Datos</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-8">Ultima actualizacion: 5 de marzo de 2026</p>

        <div class="prose dark:prose-invert max-w-none text-gray-700 dark:text-gray-300 space-y-6">

            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Su derecho a eliminar sus datos</h2>
            <p>
                De acuerdo con la Ley Federal de Proteccion de Datos Personales en Posesion de los Particulares (LFPDPPP)
                y las politicas de Meta Platforms, usted tiene derecho a solicitar la eliminacion de sus datos personales
                almacenados en nuestra plataforma.
            </p>

            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Que datos eliminamos</h2>
            <p>Al procesar su solicitud, eliminaremos:</p>
            <ul class="list-disc pl-6 space-y-1">
                <li>Su informacion de perfil y cuenta</li>
                <li>Historial de conversaciones y mensajes</li>
                <li>Datos de contacto asociados a su cuenta</li>
                <li>Registros de actividad en la plataforma</li>
                <li>Cualquier otro dato personal vinculado a su cuenta</li>
            </ul>

            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Como solicitar la eliminacion</h2>
            <p>
                Envie un correo electronico a
                <a href="mailto:jfcruz@outlook.com" class="text-indigo-600 dark:text-indigo-400">jfcruz@outlook.com</a>
                con el asunto <strong>"Solicitud de eliminacion de datos"</strong> e incluya la siguiente informacion:
            </p>
            <ul class="list-disc pl-6 space-y-1">
                <li>Nombre completo</li>
                <li>Correo electronico asociado a su cuenta</li>
                <li>Numero de telefono (si aplica)</li>
                <li>Nombre de la organizacion (si aplica)</li>
            </ul>

            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Plazo de respuesta</h2>
            <p>
                Procesaremos su solicitud en un plazo maximo de <strong>15 dias habiles</strong> a partir de la recepcion
                del correo. Le enviaremos una confirmacion una vez que sus datos hayan sido eliminados.
            </p>

            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Excepciones</h2>
            <p>
                En ciertos casos, podremos retener algunos datos cuando exista una obligacion legal que lo requiera
                (por ejemplo, registros fiscales o de facturacion). En tal caso, le informaremos que datos se
                conservaran y por cuanto tiempo.
            </p>

            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Contacto</h2>
            <p>
                Si tiene dudas sobre el proceso de eliminacion de datos, contactenos en:
                <a href="mailto:jfcruz@outlook.com" class="text-indigo-600 dark:text-indigo-400">jfcruz@outlook.com</a>
            </p>

        </div>
    </main>

    <footer class="max-w-4xl mx-auto px-6 py-8 border-t border-gray-200 dark:border-gray-700 mt-8">
        <div class="flex flex-wrap gap-4 text-sm text-gray-500 dark:text-gray-400">
            <a href="{{ route('landing') }}" class="hover:text-gray-700 dark:hover:text-gray-300">Inicio</a>
            <a href="{{ route('legal.privacy') }}" class="hover:text-gray-700 dark:hover:text-gray-300">Aviso de Privacidad</a>
            <a href="{{ route('legal.terms') }}" class="hover:text-gray-700 dark:hover:text-gray-300">Terminos de Servicio</a>
            <a href="{{ route('legal.data-deletion') }}" class="hover:text-gray-700 dark:hover:text-gray-300">Eliminacion de Datos</a>
        </div>
        <p class="text-sm text-gray-400 dark:text-gray-500 mt-4">&copy; {{ date('Y') }} ChatMe. Todos los derechos reservados.</p>
    </footer>
</body>
</html>
