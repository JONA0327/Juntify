<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Juntify - Reinventando el futuro de las reuniones</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700&display=swap" rel="stylesheet" />

    <!-- Styles -->
    @vite([
        'resources/css/app.css',
        'resources/js/app.js',
        'resources/css/index.css',
        'resources/js/index.js'
    ])
</head>
<body class="smooth-scroll">
    <!-- Animated particles background -->
    <div class="particles" id="particles"></div>

    <!-- Header -->
    <header class="header">
        <nav class="nav">
            <a href="#" class="logo">Juntify</a>
            <ul class="nav-links">
                <li><a href="#reuniones">📅 Reuniones</a></li>
                <li><a href="#nueva-reunion">➕ Nueva Reunión</a></li>
                <li><a href="#tareas">✅ Tareas</a></li>
                <li><a href="#exportar">📤 Exportar</a></li>
                <li><a href="#asistente">🤖 Asistente IA</a></li>
                <li><a href="#perfil">👤 Perfil</a></li>
            </ul>
        </nav>
    </header>

    <!-- Hero Section with 3D Sphere -->
    <section class="hero">
        <div class="hero-content">
            <!-- 3D Wireframe Sphere -->
            <div class="sphere-container">
                <div class="sphere">
                    <div class="sphere-ring"></div>
                    <div class="sphere-ring"></div>
                    <div class="sphere-ring"></div>
                    <div class="sphere-ring"></div>
                    <div class="sphere-ring"></div>
                    <div class="sphere-grid"></div>
                </div>

                <!-- Text inside sphere -->
                <div class="sphere-text">
                    <h1 class="sphere-title">Juntify</h1>
                    <p class="sphere-subtitle">Bienvenido al futuro de las reuniones.</p>
                    <a href="{{ route('login') }}" class="sphere-btn">Pruébalo gratis</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Presentation Section -->
    <section class="content-section fade-in">
        <div style="text-align: center; margin-bottom: 3rem;">
            <p style="color: #3b82f6; font-weight: 600; margin-bottom: 1rem;">Presentamos</p>
            <h2 class="section-title">Juntify</h2>
            <p class="section-subtitle">
                Imagina un mundo donde cada conversación se transforma al instante en acciones claras y decisiones precisas. Donde el tiempo que inviertes en reuniones se convierte automáticamente en tu mejor inversión estratégica. Esto es lo que hemos logrado con Juntify.
            </p>
        </div>
    </section>

    <!-- New Paradigm Section -->
    <section class="content-section fade-in">
        <h2 class="section-title">Un nuevo paradigma para tus reuniones</h2>
        <p class="section-subtitle">
            Juntify no es simplemente otra herramienta, es un nuevo paradigma para equipos que valoran la eficiencia, la precisión y la productividad elevada. Con tecnología de vanguardia que comprende el contexto, captura ideas, organiza y transforma automáticamente el tiempo invertido en reuniones en resultados tangibles.
        </p>
    </section>

    <!-- Integration Section -->
    <section class="content-section fade-in">
        <h2 class="section-title">Una experiencia perfectamente integrada</h2>
        <p class="section-subtitle">
            Nuestra plataforma se integra elegantemente diseñada y se integra perfectamente con las herramientas que ya utilizas. No necesitas adaptar tus procesos a Juntify, Juntify se adapta a ti.
        </p>

        <div class="integration-diagram">
            <div class="integration-grid">
                <div class="integration-item">
                    <div class="integration-icon" style="background: #10b981;">🎯</div>
                    <div class="integration-name">Zoom</div>
                </div>
                <div class="integration-item">
                    <div class="integration-icon" style="background: #3b82f6;">📊</div>
                    <div class="integration-name">Google Calendar</div>
                </div>
                <div class="integration-item">
                    <div class="integration-icon" style="background: #8b5cf6;">💼</div>
                    <div class="integration-name">Slack</div>
                </div>
                <div class="integration-item">
                    <div class="integration-icon" style="background: #f59e0b;">📝</div>
                    <div class="integration-name">Notion</div>
                </div>
                <div class="integration-item">
                    <div class="integration-icon" style="background: #10b981;">📋</div>
                    <div class="integration-name">Trello</div>
                </div>
                <div class="integration-item">
                    <div class="integration-icon" style="background: #ef4444;">📧</div>
                    <div class="integration-name">Gmail</div>
                </div>
                <div class="integration-item">
                    <div class="integration-icon" style="background: #8b5cf6;">🎨</div>
                    <div class="integration-name">Figma</div>
                </div>
                <div class="integration-item">
                    <div class="integration-icon" style="background: #6366f1;">📊</div>
                    <div class="integration-name">Teams</div>
                </div>
                <div class="integration-item">
                    <div class="integration-icon" style="background: #10b981;">📈</div>
                    <div class="integration-name">Asana</div>
                </div>
            </div>
            <div class="integration-center">Juntify</div>
        </div>
    </section>

    <!-- Transform Work Section -->
    <section class="content-section fade-in">
        <h2 class="section-title">Diseñado para transformar la forma en que trabajamos.</h2>
        <p class="section-subtitle">
            Cada aspecto de Juntify ha sido meticulosamente diseñado para ofrecer una experiencia excepcional y resultados tangibles.
        </p>

        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">📋</div>
                <h3 class="feature-title">Captura Perfecta</h3>
                <p class="feature-description">
                    Un sistema de captura inteligente que no se pierde ni la conversación más rápida, asegurando que cada detalle importante quede registrado automáticamente.
                </p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">💬</div>
                <h3 class="feature-title">Transcripción Precisa</h3>
                <p class="feature-description">
                    Algoritmos de última generación que convierten el audio en texto con una precisión de transcripción, críticos por contexto.
                </p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">⏰</div>
                <h3 class="feature-title">Reconocimiento de Hablantes</h3>
                <p class="feature-description">
                    Tecnología inteligente avanzada que identifica y diferencia a cada participante, incluso cuando hablan simultáneamente.
                </p>
            </div>
        </div>
    </section>

    <!-- AI Intelligence Section -->
    <section class="content-section fade-in">
        <h2 class="section-title">Inteligencia Artificial que comprende el contexto</h2>
        <p class="section-subtitle">
            Se analizan los matices detrás que no conducen a ningún lado, ahora cada encuentro tiene un propósito tangible, medible y transformador.
        </p>

        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">🧠</div>
                <h3 class="feature-title">Comprensión Contextual</h3>
                <p class="feature-description">
                    Nuestra IA analiza el contexto completo de la conversación, identificando temas clave, decisiones y acciones de manera inteligente.
                </p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">😊</div>
                <h3 class="feature-title">Extracción Inteligente</h3>
                <p class="feature-description">
                    Identifica automáticamente los puntos clave, tareas, compromisos y decisiones importantes sin intervención manual.
                </p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">📊</div>
                <h3 class="feature-title">Asignación Precisa</h3>
                <p class="feature-description">
                    Asigna automáticamente tareas y plazos con base en el contexto de la conversación y los participantes involucrados.
                </p>
            </div>
        </div>
    </section>

    <!-- Pricing Section -->
    <section class="pricing-section fade-in">
        <div class="content-section">
            <h2 class="section-title">Planes de Reuniones</h2>
            <p class="section-subtitle">
                La claridad que siempre quisiste para tus reuniones ya está aquí. Es tiempo de abandonar, simplificar la comunicación y hacer que cada minuto cuente.
            </p>

            <p style="text-align: center; color: #cbd5e1; margin-bottom: 2rem;">
                Elige tu plan de facturación preferida
            </p>

            <div class="pricing-toggle">
                <button class="toggle-btn active">Anual</button>
                <button class="toggle-btn">Mensual</button>
                <button class="toggle-btn">Reuniones Individuales</button>
            </div>

            <div class="pricing-grid">
                <div class="pricing-card">
                    <h3 class="pricing-title">Freemium</h3>
                    <div class="pricing-price">$0</div>
                    <div class="pricing-period">mes</div>
                    <p style="color: #cbd5e1; margin-bottom: 2rem; font-size: 0.9rem;">
                        Ideal para uso personal y equipos pequeños que buscan optimizar sus reuniones básicas.
                    </p>
                    <ul class="pricing-features">
                        <li>Hasta 3 reuniones por mes</li>
                        <li>Transcripción básica</li>
                        <li>Resúmenes automáticos (30 minutos)</li>
                        <li>Exportar como texto</li>
                        <li>Soporte por email</li>
                    </ul>
                    <a href="#" class="pricing-btn secondary">Empezar gratis</a>
                </div>

                <div class="pricing-card popular">
                    <h3 class="pricing-title">Básico</h3>
                    <div class="pricing-price">$499</div>
                    <div class="pricing-period">mes</div>
                    <p style="color: #cbd5e1; margin-bottom: 2rem; font-size: 0.9rem;">
                        Ideal para equipos medianos que buscan optimizar sus reuniones y aumentar la productividad.
                    </p>
                    <ul class="pricing-features">
                        <li>Reuniones ilimitadas</li>
                        <li>Transcripción avanzada</li>
                        <li>Resúmenes inteligentes</li>
                        <li>Identificación de hablantes</li>
                        <li>Exportar múltiples formatos</li>
                        <li>Integraciones básicas</li>
                        <li>Soporte prioritario</li>
                    </ul>
                    <a href="#" class="pricing-btn">Seleccionar Plan</a>
                </div>

                <div class="pricing-card">
                    <h3 class="pricing-title">Negocios</h3>
                    <div class="pricing-price">$999</div>
                    <div class="pricing-period">mes</div>
                    <p style="color: #cbd5e1; margin-bottom: 2rem; font-size: 0.9rem;">
                        Ideal para empresas que buscan una solución completa para optimizar todas sus reuniones.
                    </p>
                    <ul class="pricing-features">
                        <li>Todo lo del plan Básico</li>
                        <li>IA avanzada para análisis</li>
                        <li>Análisis de sentimientos</li>
                        <li>Dashboards ejecutivos</li>
                        <li>Integraciones avanzadas</li>
                        <li>API personalizada</li>
                        <li>Soporte 24/7</li>
                        <li>Capacitación incluida</li>
                    </ul>
                    <a href="#" class="pricing-btn">Seleccionar Plan</a>
                </div>

                <div class="pricing-card">
                    <h3 class="pricing-title">Empresas</h3>
                    <div class="pricing-price">$2999</div>
                    <div class="pricing-period">mes</div>
                    <p style="color: #cbd5e1; margin-bottom: 2rem; font-size: 0.9rem;">
                        Ideal para grandes empresas y corporaciones que necesitan máxima personalización y control.
                    </p>
                    <ul class="pricing-features">
                        <li>Todo lo del plan Negocios</li>
                        <li>Implementación personalizada</li>
                        <li>Seguridad empresarial</li>
                        <li>Cumplimiento normativo</li>
                        <li>Análisis predictivo avanzado</li>
                        <li>Integraciones ilimitadas</li>
                        <li>Gerente de cuenta dedicado</li>
                        <li>SLA garantizado</li>
                    </ul>
                    <a href="#" class="pricing-btn">Contactar Ventas</a>
                </div>
            </div>

            <p style="text-align: center; color: #cbd5e1; margin-top: 3rem; font-size: 0.9rem;">
                ¿Quieres probar antes de comprar? Hay créditos de reunión disponibles a<br>
                miembros premium completamente gratis, para que experimentes por ti mismo el<br>
                poder transformador de Juntify.
            </p>
        </div>
    </section>

</body>
</html>
