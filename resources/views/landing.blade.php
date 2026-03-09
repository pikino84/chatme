<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ChatMe - Plataforma de Mensajería Omnicanal para Empresas</title>
    <meta name="description" content="Gestiona WhatsApp, webchat y más desde un solo lugar. CRM integrado, automatizaciones, campañas y reportes en tiempo real.">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700,800&display=swap" rel="stylesheet" />
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', system-ui, sans-serif; color: #1a1a2e; background: #ffffff; -webkit-font-smoothing: antialiased; }

        /* Navbar */
        .navbar { position: fixed; top: 0; width: 100%; background: rgba(255,255,255,0.95); backdrop-filter: blur(12px); border-bottom: 1px solid #f0f0f5; z-index: 100; }
        .navbar-inner { max-width: 1200px; margin: 0 auto; padding: 16px 24px; display: flex; align-items: center; justify-content: space-between; }
        .logo { font-size: 24px; font-weight: 800; color: #4f46e5; text-decoration: none; display: flex; align-items: center; gap: 8px; }
        .logo-icon { width: 32px; height: 32px; background: linear-gradient(135deg, #4f46e5, #7c3aed); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: white; font-size: 16px; }
        .nav-links { display: flex; align-items: center; gap: 32px; }
        .nav-links a { text-decoration: none; font-size: 14px; font-weight: 500; color: #64748b; transition: color 0.2s; }
        .nav-links a:hover { color: #4f46e5; }
        .btn { display: inline-flex; align-items: center; justify-content: center; padding: 10px 24px; border-radius: 8px; font-size: 14px; font-weight: 600; text-decoration: none; transition: all 0.2s; border: none; cursor: pointer; }
        .btn-ghost { color: #4f46e5; background: transparent; }
        .btn-ghost:hover { background: #f0f0ff; }
        .btn-primary { color: white; background: linear-gradient(135deg, #4f46e5, #7c3aed); box-shadow: 0 4px 14px rgba(79,70,229,0.35); }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(79,70,229,0.4); }
        .btn-lg { padding: 14px 36px; font-size: 16px; border-radius: 10px; }
        .btn-outline { color: #4f46e5; background: transparent; border: 2px solid #4f46e5; }
        .btn-outline:hover { background: #4f46e5; color: white; }

        /* Hero */
        .hero { padding: 140px 24px 80px; text-align: center; background: linear-gradient(180deg, #f8f7ff 0%, #ffffff 100%); position: relative; overflow: hidden; }
        .hero::before { content: ''; position: absolute; top: -200px; left: 50%; transform: translateX(-50%); width: 800px; height: 800px; background: radial-gradient(circle, rgba(79,70,229,0.08) 0%, transparent 70%); pointer-events: none; }
        .hero-badge { display: inline-flex; align-items: center; gap: 8px; padding: 6px 16px; background: #f0f0ff; color: #4f46e5; border-radius: 100px; font-size: 13px; font-weight: 600; margin-bottom: 24px; border: 1px solid #e0e0ff; }
        .hero h1 { font-size: clamp(36px, 5vw, 64px); font-weight: 800; line-height: 1.1; max-width: 800px; margin: 0 auto 24px; background: linear-gradient(135deg, #1a1a2e 0%, #4f46e5 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
        .hero p { font-size: 18px; line-height: 1.7; color: #64748b; max-width: 600px; margin: 0 auto 40px; }
        .hero-actions { display: flex; gap: 16px; justify-content: center; flex-wrap: wrap; }
        .hero-stats { display: flex; justify-content: center; gap: 48px; margin-top: 64px; padding-top: 48px; border-top: 1px solid #f0f0f5; }
        .hero-stat { text-align: center; }
        .hero-stat-value { font-size: 32px; font-weight: 800; color: #4f46e5; }
        .hero-stat-label { font-size: 13px; color: #94a3b8; margin-top: 4px; }

        /* Sections */
        .section { padding: 96px 24px; }
        .section-alt { background: #f8f9fc; }
        .section-header { text-align: center; max-width: 700px; margin: 0 auto 64px; }
        .section-header h2 { font-size: 36px; font-weight: 800; margin-bottom: 16px; color: #1a1a2e; }
        .section-header p { font-size: 16px; color: #64748b; line-height: 1.6; }
        .section-tag { display: inline-block; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px; color: #4f46e5; background: #f0f0ff; padding: 4px 12px; border-radius: 4px; margin-bottom: 12px; }

        /* Features Grid */
        .features-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 32px; max-width: 1200px; margin: 0 auto; }
        .feature-card { background: white; border: 1px solid #f0f0f5; border-radius: 16px; padding: 36px; transition: all 0.3s; }
        .feature-card:hover { border-color: #e0e0ff; box-shadow: 0 8px 30px rgba(79,70,229,0.08); transform: translateY(-4px); }
        .feature-icon { width: 52px; height: 52px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px; margin-bottom: 20px; }
        .feature-card h3 { font-size: 18px; font-weight: 700; margin-bottom: 10px; color: #1a1a2e; }
        .feature-card p { font-size: 14px; color: #64748b; line-height: 1.6; }

        /* Channels */
        .channels { display: flex; justify-content: center; gap: 40px; flex-wrap: wrap; max-width: 900px; margin: 0 auto; }
        .channel-item { display: flex; flex-direction: column; align-items: center; gap: 12px; padding: 32px; border-radius: 16px; background: white; border: 1px solid #f0f0f5; min-width: 160px; transition: all 0.3s; }
        .channel-item:hover { border-color: #e0e0ff; box-shadow: 0 8px 30px rgba(79,70,229,0.08); }
        .channel-icon { width: 56px; height: 56px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 28px; }
        .channel-item span { font-size: 14px; font-weight: 600; color: #1a1a2e; }

        /* Pricing */
        .pricing-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px; max-width: 1000px; margin: 0 auto; }
        .pricing-card { background: white; border: 1px solid #f0f0f5; border-radius: 16px; padding: 40px 32px; position: relative; }
        .pricing-card.popular { border-color: #4f46e5; box-shadow: 0 8px 30px rgba(79,70,229,0.15); }
        .pricing-badge { position: absolute; top: -12px; left: 50%; transform: translateX(-50%); background: linear-gradient(135deg, #4f46e5, #7c3aed); color: white; font-size: 12px; font-weight: 700; padding: 4px 16px; border-radius: 100px; }
        .pricing-card h3 { font-size: 20px; font-weight: 700; margin-bottom: 8px; }
        .pricing-card .price { font-size: 42px; font-weight: 800; color: #1a1a2e; margin: 16px 0; }
        .pricing-card .price span { font-size: 16px; font-weight: 500; color: #94a3b8; }
        .pricing-card .price-note { font-size: 13px; color: #94a3b8; margin-bottom: 24px; }
        .pricing-features { list-style: none; margin-bottom: 32px; }
        .pricing-features li { font-size: 14px; color: #475569; padding: 8px 0; display: flex; align-items: center; gap: 10px; border-bottom: 1px solid #f8f9fc; }
        .pricing-features li::before { content: '\2713'; color: #4f46e5; font-weight: 700; font-size: 13px; flex-shrink: 0; }
        .pricing-features li.disabled { color: #cbd5e1; }
        .pricing-features li.disabled::before { content: '\2717'; color: #e2e8f0; }

        /* CTA */
        .cta { padding: 96px 24px; text-align: center; background: linear-gradient(135deg, #4f46e5, #7c3aed); color: white; }
        .cta h2 { font-size: 36px; font-weight: 800; margin-bottom: 16px; }
        .cta p { font-size: 18px; opacity: 0.85; max-width: 500px; margin: 0 auto 36px; }
        .btn-white { background: white; color: #4f46e5; }
        .btn-white:hover { background: #f8f7ff; transform: translateY(-1px); }

        /* Footer */
        .footer { background: #0f172a; color: #94a3b8; padding: 64px 24px 32px; }
        .footer-inner { max-width: 1200px; margin: 0 auto; display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 48px; }
        .footer h4 { color: white; font-size: 14px; font-weight: 700; margin-bottom: 16px; }
        .footer a { color: #94a3b8; text-decoration: none; font-size: 14px; display: block; padding: 4px 0; transition: color 0.2s; }
        .footer a:hover { color: white; }
        .footer-brand p { font-size: 14px; line-height: 1.6; margin-top: 12px; }
        .footer-bottom { max-width: 1200px; margin: 48px auto 0; padding-top: 24px; border-top: 1px solid #1e293b; text-align: center; font-size: 13px; }

        /* Responsive */
        @media (max-width: 768px) {
            .nav-links { display: none; }
            .hero-stats { flex-direction: column; gap: 24px; }
            .footer-inner { grid-template-columns: 1fr; gap: 32px; }
            .features-grid { grid-template-columns: 1fr; }
            .pricing-grid { grid-template-columns: 1fr; max-width: 400px; }
        }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar">
    <div class="navbar-inner">
        <a href="/" class="logo">
            <div class="logo-icon">C</div>
            ChatMe
        </a>
        <div class="nav-links">
            <a href="#funciones">Funciones</a>
            <a href="#canales">Canales</a>
            <a href="#precios">Precios</a>
            <a href="{{ 'https://app.' . config('app.base_domain') . '/login' }}" class="btn btn-ghost">Iniciar Sesión</a>
            <a href="{{ 'https://app.' . config('app.base_domain') . '/register' }}" class="btn btn-primary">Prueba Gratis</a>
        </div>
    </div>
</nav>

<!-- Hero -->
<section class="hero">
    <div class="hero-badge">Nuevo: Campañas masivas y secuencias drip</div>
    <h1>Todas tus conversaciones en un solo lugar</h1>
    <p>La plataforma todo-en-uno para gestionar WhatsApp, webchat y redes sociales. CRM integrado, automatizaciones inteligentes y reportes en tiempo real para equipos que venden y atienden por chat.</p>
    <div class="hero-actions">
        <a href="{{ 'https://app.' . config('app.base_domain') . '/register' }}" class="btn btn-primary btn-lg">Comenzar 14 días gratis</a>
        <a href="#funciones" class="btn btn-outline btn-lg">Ver funciones</a>
    </div>
    <div class="hero-stats">
        <div class="hero-stat">
            <div class="hero-stat-value">5+</div>
            <div class="hero-stat-label">Canales integrados</div>
        </div>
        <div class="hero-stat">
            <div class="hero-stat-value">100%</div>
            <div class="hero-stat-label">Multi-tenant</div>
        </div>
        <div class="hero-stat">
            <div class="hero-stat-value">24/7</div>
            <div class="hero-stat-label">Automatizaciones activas</div>
        </div>
    </div>
</section>

<!-- Features -->
<section class="section" id="funciones">
    <div class="section-header">
        <div class="section-tag">Funciones</div>
        <h2>Todo lo que necesitas para vender y atender por chat</h2>
        <p>Desde la primera conversación hasta el cierre de venta, ChatMe centraliza todo tu flujo de comunicación con clientes.</p>
    </div>
    <div class="features-grid">
        <div class="feature-card">
            <div class="feature-icon" style="background:#f0f0ff;color:#4f46e5;">&#128172;</div>
            <h3>Bandeja Unificada</h3>
            <p>Todas las conversaciones de WhatsApp, webchat, Facebook e Instagram en una sola bandeja. Asigna agentes, transfiere chats y monitorea en tiempo real.</p>
        </div>
        <div class="feature-card">
            <div class="feature-icon" style="background:#fef3c7;color:#d97706;">&#128200;</div>
            <h3>CRM Kanban</h3>
            <p>Convierte conversaciones en deals. Pipeline visual tipo Kanban con etapas personalizables, notas, archivos adjuntos y comisiones por agente.</p>
        </div>
        <div class="feature-card">
            <div class="feature-icon" style="background:#dcfce7;color:#16a34a;">&#9889;</div>
            <h3>Automatizaciones</h3>
            <p>Auto-asignación inteligente (round-robin o menos ocupado), respuestas automáticas de bienvenida y alertas por conversaciones sin atender.</p>
        </div>
        <div class="feature-card">
            <div class="feature-icon" style="background:#fce7f3;color:#db2777;">&#128227;</div>
            <h3>Campañas Broadcast</h3>
            <p>Envía mensajes masivos a tus contactos por WhatsApp. Selecciona destinatarios, programa envíos y monitorea tasas de entrega en tiempo real.</p>
        </div>
        <div class="feature-card">
            <div class="feature-icon" style="background:#e0e7ff;color:#4338ca;">&#128337;</div>
            <h3>Secuencias Drip</h3>
            <p>Crea secuencias automatizadas de seguimiento. Define pasos con intervalos de tiempo y mensajes personalizados que se envían automáticamente.</p>
        </div>
        <div class="feature-card">
            <div class="feature-icon" style="background:#f0fdfa;color:#0d9488;">&#128101;</div>
            <h3>Gestión de Contactos</h3>
            <p>Base de contactos centralizada con importación CSV, fusión de duplicados, historial de conversaciones y deals asociados.</p>
        </div>
        <div class="feature-card">
            <div class="feature-icon" style="background:#fef9c3;color:#ca8a04;">&#128218;</div>
            <h3>Base de Conocimiento</h3>
            <p>Crea artículos organizados por categorías para que tus agentes encuentren respuestas al instante. Integración con IA para respuestas automáticas.</p>
        </div>
        <div class="feature-card">
            <div class="feature-icon" style="background:#ede9fe;color:#7c3aed;">&#129302;</div>
            <h3>Asistente IA</h3>
            <p>Respuestas inteligentes basadas en tu base de conocimiento usando OpenAI. Sugiere respuestas a tus agentes o responde automáticamente.</p>
        </div>
        <div class="feature-card">
            <div class="feature-icon" style="background:#fff1f2;color:#e11d48;">&#128202;</div>
            <h3>Analytics y Reportes</h3>
            <p>Dashboard con métricas clave: tiempos de respuesta, volumen de conversaciones, rendimiento por agente, SLA y exportación a CSV.</p>
        </div>
    </div>
</section>

<!-- Channels -->
<section class="section section-alt" id="canales">
    <div class="section-header">
        <div class="section-tag">Canales</div>
        <h2>Conecta todos tus canales de comunicación</h2>
        <p>Integra los canales que tus clientes ya usan y gestiónalos desde una sola plataforma.</p>
    </div>
    <div class="channels">
        <div class="channel-item">
            <div class="channel-icon" style="background:#dcfce7;color:#16a34a;">&#128172;</div>
            <span>WhatsApp</span>
        </div>
        <div class="channel-item">
            <div class="channel-icon" style="background:#e0e7ff;color:#4338ca;">&#128187;</div>
            <span>Webchat</span>
        </div>
        <div class="channel-item">
            <div class="channel-icon" style="background:#dbeafe;color:#2563eb;">&#128077;</div>
            <span>Facebook</span>
        </div>
        <div class="channel-item">
            <div class="channel-icon" style="background:#fce7f3;color:#db2777;">&#128247;</div>
            <span>Instagram</span>
        </div>
        <div class="channel-item">
            <div class="channel-icon" style="background:#fef3c7;color:#d97706;">&#9993;</div>
            <span>Email</span>
        </div>
    </div>
</section>

<!-- How it works -->
<section class="section">
    <div class="section-header">
        <div class="section-tag">Como funciona</div>
        <h2>En 3 pasos estás listo</h2>
        <p>Configura tu cuenta en minutos y empieza a atender clientes de inmediato.</p>
    </div>
    <div class="features-grid" style="max-width:900px;margin:0 auto;">
        <div class="feature-card" style="text-align:center;">
            <div style="font-size:48px;margin-bottom:16px;">1</div>
            <h3>Crea tu cuenta</h3>
            <p>Regístrate gratis con 14 días de prueba. Sin tarjeta de crédito, sin compromisos.</p>
        </div>
        <div class="feature-card" style="text-align:center;">
            <div style="font-size:48px;margin-bottom:16px;">2</div>
            <h3>Conecta tus canales</h3>
            <p>Vincula tu WhatsApp Business, instala el webchat en tu sitio web o conecta tus redes sociales.</p>
        </div>
        <div class="feature-card" style="text-align:center;">
            <div style="font-size:48px;margin-bottom:16px;">3</div>
            <h3>Atiende y vende</h3>
            <p>Tu equipo recibe todas las conversaciones en un solo lugar. Asigna, responde y cierra ventas.</p>
        </div>
    </div>
</section>

<!-- Pricing -->
<section class="section section-alt" id="precios">
    <div class="section-header">
        <div class="section-tag">Precios</div>
        <h2>Planes que crecen contigo</h2>
        <p>Empieza gratis y escala según las necesidades de tu equipo. Sin sorpresas, sin costos ocultos.</p>
    </div>
    <div class="pricing-grid">
        <!-- Starter -->
        <div class="pricing-card">
            <h3>Starter</h3>
            <p style="font-size:14px;color:#94a3b8;margin-bottom:8px;">Para equipos pequeños que comienzan</p>
            <div class="price">$499<span>/mes</span></div>
            <div class="price-note">$4,999/año (ahorra 2 meses)</div>
            <ul class="pricing-features">
                <li>3 agentes</li>
                <li>1 canal (WhatsApp)</li>
                <li>100 conversaciones/mes</li>
                <li>100 contactos</li>
                <li>Base de conocimiento (20 artículos)</li>
                <li class="disabled">Webchat</li>
                <li class="disabled">Campañas broadcast</li>
                <li class="disabled">Automatizaciones</li>
                <li class="disabled">Analytics y reportes</li>
                <li class="disabled">Asistente IA</li>
            </ul>
            <a href="{{ 'https://app.' . config('app.base_domain') . '/register' }}" class="btn btn-outline" style="width:100%;">Comenzar Gratis</a>
        </div>

        <!-- Professional -->
        <div class="pricing-card popular">
            <div class="pricing-badge">Más popular</div>
            <h3>Professional</h3>
            <p style="font-size:14px;color:#94a3b8;margin-bottom:8px;">Para equipos en crecimiento</p>
            <div class="price">$999<span>/mes</span></div>
            <div class="price-note">$9,999/año (ahorra 2 meses)</div>
            <ul class="pricing-features">
                <li>10 agentes</li>
                <li>5 canales</li>
                <li>1,000 conversaciones/mes</li>
                <li>5,000 contactos</li>
                <li>Base de conocimiento (200 artículos)</li>
                <li>Webchat en tu sitio web</li>
                <li>Campañas broadcast (10/mes)</li>
                <li>Secuencias drip</li>
                <li>Automatizaciones</li>
                <li>Analytics y reportes</li>
                <li>SLA y monitoreo</li>
                <li>Asistente IA (500 consultas/mes)</li>
            </ul>
            <a href="{{ 'https://app.' . config('app.base_domain') . '/register' }}" class="btn btn-primary" style="width:100%;">Probar 14 días gratis</a>
        </div>

        <!-- Enterprise -->
        <div class="pricing-card">
            <h3>Enterprise</h3>
            <p style="font-size:14px;color:#94a3b8;margin-bottom:8px;">Para empresas con necesidades avanzadas</p>
            <div class="price">$2,499<span>/mes</span></div>
            <div class="price-note">$24,999/año (ahorra 2 meses)</div>
            <ul class="pricing-features">
                <li>Agentes ilimitados</li>
                <li>Canales ilimitados</li>
                <li>Conversaciones ilimitadas</li>
                <li>Contactos ilimitados</li>
                <li>Base de conocimiento ilimitada</li>
                <li>Webchat con branding propio</li>
                <li>Campañas ilimitadas</li>
                <li>Secuencias drip</li>
                <li>Automatizaciones avanzadas</li>
                <li>Analytics y reportes</li>
                <li>API REST</li>
                <li>Asistente IA ilimitado</li>
            </ul>
            <a href="{{ 'https://app.' . config('app.base_domain') . '/register' }}" class="btn btn-outline" style="width:100%;">Contactar Ventas</a>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="cta">
    <h2>Empieza a vender más por chat hoy</h2>
    <p>14 días de prueba gratis. Sin tarjeta de crédito. Configura tu cuenta en menos de 5 minutos.</p>
    <a href="{{ 'https://app.' . config('app.base_domain') . '/register' }}" class="btn btn-white btn-lg">Crear cuenta gratis</a>
</section>

<!-- Footer -->
<footer class="footer">
    <div class="footer-inner">
        <div class="footer-brand">
            <div style="font-size:20px;font-weight:800;color:white;margin-bottom:4px;">ChatMe</div>
            <p>Plataforma de mensajería omnicanal para equipos de ventas y atención al cliente en Latinoamérica.</p>
        </div>
        <div>
            <h4>Producto</h4>
            <a href="#funciones">Funciones</a>
            <a href="#canales">Canales</a>
            <a href="#precios">Precios</a>
        </div>
        <div>
            <h4>Legal</h4>
            <a href="{{ route('legal.privacy') }}">Política de Privacidad</a>
            <a href="{{ route('legal.terms') }}">Términos de Servicio</a>
            <a href="{{ route('legal.data-deletion') }}">Eliminación de Datos</a>
        </div>
        <div>
            <h4>Soporte</h4>
            <a href="mailto:soporte@chatme.com.mx">soporte@chatme.com.mx</a>
            <a href="{{ 'https://app.' . config('app.base_domain') . '/login' }}">Iniciar Sesión</a>
        </div>
    </div>
    <div class="footer-bottom">
        &copy; {{ date('Y') }} ChatMe. Todos los derechos reservados.
    </div>
</footer>

</body>
</html>
