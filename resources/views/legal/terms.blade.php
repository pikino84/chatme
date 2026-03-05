<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Terminos de Servicio - ChatMe</title>
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
        <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-2">Terminos de Servicio</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-8">Ultima actualizacion: 5 de marzo de 2026</p>

        <div class="prose dark:prose-invert max-w-none text-gray-700 dark:text-gray-300 space-y-6">

            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">1. Aceptacion de los terminos</h2>
            <p>
                Al registrarse y utilizar la plataforma ChatMe, operada por <strong>Crea Espacios</strong>,
                usted acepta estos terminos de servicio en su totalidad. Si no esta de acuerdo con alguno de
                estos terminos, no utilice la plataforma.
            </p>

            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">2. Descripcion del servicio</h2>
            <p>
                ChatMe es una plataforma de mensajeria omnicanal que permite a las empresas gestionar
                conversaciones con sus clientes a traves de multiples canales de comunicacion, incluyendo
                WhatsApp, Facebook Messenger, Instagram y Webchat.
            </p>

            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">3. Registro y cuenta</h2>
            <ul class="list-disc pl-6 space-y-1">
                <li>Debe proporcionar informacion veraz y actualizada al registrarse</li>
                <li>Es responsable de mantener la confidencialidad de sus credenciales de acceso</li>
                <li>Debe notificarnos inmediatamente sobre cualquier uso no autorizado de su cuenta</li>
                <li>Una cuenta corresponde a una organizacion; puede tener multiples usuarios por organizacion</li>
            </ul>

            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">4. Planes y pagos</h2>
            <ul class="list-disc pl-6 space-y-1">
                <li>Los precios y caracteristicas de cada plan se publican en la plataforma</li>
                <li>Las suscripciones se cobran de forma recurrente segun el plan contratado</li>
                <li>Nos reservamos el derecho de modificar los precios con previo aviso de 30 dias</li>
                <li>La falta de pago podra resultar en la suspension del servicio</li>
            </ul>

            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">5. Uso aceptable</h2>
            <p>Al utilizar ChatMe, usted se compromete a:</p>
            <ul class="list-disc pl-6 space-y-1">
                <li>Cumplir con las leyes aplicables de Mexico y cualquier jurisdiccion relevante</li>
                <li>Cumplir con las politicas de uso de los canales conectados (Meta, WhatsApp, etc.)</li>
                <li>No enviar mensajes no solicitados (spam) a traves de la plataforma</li>
                <li>No utilizar la plataforma para actividades ilegales, fraudulentas o abusivas</li>
                <li>No intentar acceder a datos de otras organizaciones en la plataforma</li>
                <li>No realizar ingenieria inversa ni intentar vulnerar la seguridad del sistema</li>
            </ul>

            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">6. Propiedad intelectual</h2>
            <p>
                La plataforma ChatMe, incluyendo su codigo, diseno, marca y contenido, es propiedad de Crea Espacios.
                El uso de la plataforma no le otorga ningun derecho de propiedad intelectual sobre la misma.
                Los datos y contenido que usted suba a la plataforma siguen siendo de su propiedad.
            </p>

            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">7. Disponibilidad del servicio</h2>
            <p>
                Nos esforzamos por mantener la plataforma disponible de manera continua; sin embargo,
                no garantizamos una disponibilidad del 100%. Podemos realizar mantenimientos programados
                y notificaremos con anticipacion cuando sea posible.
            </p>

            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">8. Limitacion de responsabilidad</h2>
            <p>
                Crea Espacios no sera responsable por danos indirectos, incidentales o consecuentes
                derivados del uso de la plataforma, incluyendo pero no limitado a: perdida de datos,
                interrupciones del servicio de terceros (Meta, WhatsApp, etc.) o fallos en la entrega de mensajes.
            </p>

            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">9. Cancelacion</h2>
            <ul class="list-disc pl-6 space-y-1">
                <li>Puede cancelar su cuenta en cualquier momento desde la configuracion de la plataforma</li>
                <li>Al cancelar, sus datos seran eliminados conforme a nuestro aviso de privacidad</li>
                <li>Nos reservamos el derecho de suspender o cancelar cuentas que violen estos terminos</li>
            </ul>

            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">10. Modificaciones</h2>
            <p>
                Nos reservamos el derecho de modificar estos terminos en cualquier momento.
                Los cambios seran publicados en esta pagina con la fecha de actualizacion.
                El uso continuado de la plataforma despues de los cambios constituye su aceptacion.
            </p>

            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">11. Legislacion aplicable</h2>
            <p>
                Estos terminos se rigen por las leyes de los Estados Unidos Mexicanos.
                Cualquier controversia sera resuelta ante los tribunales competentes de Mexico.
            </p>

            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">12. Contacto</h2>
            <p>
                Para cualquier duda sobre estos terminos, contactenos en:
                <a href="mailto:jfcruz@outlook.com" class="text-indigo-600 dark:text-indigo-400">jfcruz@outlook.com</a>
            </p>

        </div>
    </main>

    <footer class="max-w-4xl mx-auto px-6 py-8 border-t border-gray-200 dark:border-gray-700 mt-8">
        <div class="flex flex-wrap gap-4 text-sm text-gray-500 dark:text-gray-400">
            <a href="{{ route('landing') }}" class="hover:text-gray-700 dark:hover:text-gray-300">Inicio</a>
            <a href="{{ route('legal.privacy') }}" class="hover:text-gray-700 dark:hover:text-gray-300">Aviso de Privacidad</a>
            <a href="{{ route('legal.terms') }}" class="hover:text-gray-700 dark:hover:text-gray-300">Terminos de Servicio</a>
        </div>
        <p class="text-sm text-gray-400 dark:text-gray-500 mt-4">&copy; {{ date('Y') }} Crea Espacios. Todos los derechos reservados.</p>
    </footer>
</body>
</html>
