<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Aviso de Privacidad - ChatMe</title>
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
        <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-2">Aviso de Privacidad</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-8">Ultima actualizacion: 5 de marzo de 2026</p>

        <div class="prose dark:prose-invert max-w-none text-gray-700 dark:text-gray-300 space-y-6">

            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">1. Responsable del tratamiento de datos</h2>
            <p>
                <strong>ChatMe</strong>, con domicilio en Mexico, es responsable del tratamiento de sus datos personales.
                Para cualquier consulta relacionada con la proteccion de sus datos puede contactarnos en:
                <a href="mailto:jfcruz@outlook.com" class="text-indigo-600 dark:text-indigo-400">jfcruz@outlook.com</a>.
            </p>

            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">2. Datos personales que recabamos</h2>
            <p>ChatMe recaba los siguientes datos personales:</p>
            <ul class="list-disc pl-6 space-y-1">
                <li>Nombre completo</li>
                <li>Direccion de correo electronico</li>
                <li>Numero de telefono</li>
                <li>Nombre de la organizacion o negocio</li>
                <li>Mensajes enviados y recibidos a traves de los canales conectados (WhatsApp, Facebook Messenger, Instagram, Webchat)</li>
                <li>Datos de uso de la plataforma (registros de actividad, direccion IP, navegador)</li>
            </ul>

            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">3. Finalidades del tratamiento</h2>
            <p>Sus datos personales seran utilizados para las siguientes finalidades:</p>
            <ul class="list-disc pl-6 space-y-1">
                <li>Crear y administrar su cuenta en la plataforma</li>
                <li>Proveer el servicio de mensajeria omnicanal</li>
                <li>Procesar y gestionar conversaciones con sus clientes</li>
                <li>Facturacion y cobro de suscripciones</li>
                <li>Enviar notificaciones relacionadas con el servicio</li>
                <li>Mejorar la calidad del servicio y la experiencia del usuario</li>
                <li>Cumplir con obligaciones legales aplicables</li>
            </ul>

            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">4. Fundamento legal</h2>
            <p>
                El tratamiento de sus datos personales se realiza con fundamento en la
                Ley Federal de Proteccion de Datos Personales en Posesion de los Particulares (LFPDPPP)
                y su Reglamento, vigentes en los Estados Unidos Mexicanos.
            </p>

            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">5. Transferencia de datos</h2>
            <p>
                Para la prestacion del servicio, sus datos podran ser compartidos con los siguientes terceros:
            </p>
            <ul class="list-disc pl-6 space-y-1">
                <li><strong>Meta Platforms, Inc.</strong> (WhatsApp Business API, Facebook Messenger, Instagram) para el envio y recepcion de mensajes</li>
                <li><strong>Proveedores de infraestructura</strong> (servidores y almacenamiento en la nube) para el alojamiento de la plataforma</li>
                <li><strong>Procesadores de pago</strong> para la gestion de cobros y suscripciones</li>
            </ul>
            <p>
                No vendemos, alquilamos ni comercializamos sus datos personales con terceros para fines de marketing.
            </p>

            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">6. Derechos ARCO</h2>
            <p>
                Usted tiene derecho a Acceder, Rectificar, Cancelar u Oponerse al tratamiento de sus datos personales
                (derechos ARCO). Para ejercer cualquiera de estos derechos, envie una solicitud a
                <a href="mailto:jfcruz@outlook.com" class="text-indigo-600 dark:text-indigo-400">jfcruz@outlook.com</a>
                con la siguiente informacion:
            </p>
            <ul class="list-disc pl-6 space-y-1">
                <li>Nombre completo del titular</li>
                <li>Descripcion clara del derecho que desea ejercer</li>
                <li>Correo electronico asociado a su cuenta</li>
            </ul>
            <p>Daremos respuesta a su solicitud en un plazo maximo de 20 dias habiles.</p>

            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">7. Medidas de seguridad</h2>
            <p>
                Implementamos medidas de seguridad tecnicas, administrativas y fisicas para proteger sus datos personales
                contra dano, perdida, alteracion, destruccion o acceso no autorizado. Entre estas medidas se incluyen:
            </p>
            <ul class="list-disc pl-6 space-y-1">
                <li>Cifrado de datos en transito (HTTPS/TLS) y en reposo</li>
                <li>Autenticacion de dos factores (2FA)</li>
                <li>Aislamiento de datos entre organizaciones (multi-tenant)</li>
                <li>Validacion de firmas HMAC en webhooks</li>
            </ul>

            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">8. Uso de cookies</h2>
            <p>
                ChatMe utiliza cookies estrictamente necesarias para el funcionamiento de la plataforma
                (sesion de usuario y token CSRF). No utilizamos cookies de rastreo ni de publicidad.
            </p>

            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">9. Conservacion de datos</h2>
            <p>
                Sus datos personales seran conservados mientras mantenga una cuenta activa en la plataforma.
                Al cancelar su cuenta, sus datos seran eliminados en un plazo razonable, salvo aquellos que debamos
                conservar por obligacion legal.
            </p>

            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">10. Modificaciones al aviso de privacidad</h2>
            <p>
                Nos reservamos el derecho de modificar este aviso de privacidad en cualquier momento.
                Las modificaciones seran publicadas en esta misma pagina con la fecha de actualizacion correspondiente.
                Le recomendamos revisarla periodicamente.
            </p>

            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">11. Contacto</h2>
            <p>
                Si tiene alguna duda o comentario sobre este aviso de privacidad, puede contactarnos en:
            </p>
            <ul class="list-disc pl-6 space-y-1">
                <li><strong>Correo:</strong> <a href="mailto:jfcruz@outlook.com" class="text-indigo-600 dark:text-indigo-400">jfcruz@outlook.com</a></li>
                <li><strong>Plataforma:</strong> <a href="https://chatme.com.mx" class="text-indigo-600 dark:text-indigo-400">chatme.com.mx</a></li>
            </ul>

        </div>
    </main>

    <footer class="max-w-4xl mx-auto px-6 py-8 border-t border-gray-200 dark:border-gray-700 mt-8">
        <div class="flex flex-wrap gap-4 text-sm text-gray-500 dark:text-gray-400">
            <a href="{{ route('landing') }}" class="hover:text-gray-700 dark:hover:text-gray-300">Inicio</a>
            <a href="{{ route('legal.privacy') }}" class="hover:text-gray-700 dark:hover:text-gray-300">Aviso de Privacidad</a>
            <a href="{{ route('legal.terms') }}" class="hover:text-gray-700 dark:hover:text-gray-300">Terminos de Servicio</a>
        </div>
        <p class="text-sm text-gray-400 dark:text-gray-500 mt-4">&copy; {{ date('Y') }} ChatMe. Todos los derechos reservados.</p>
    </footer>
</body>
</html>
