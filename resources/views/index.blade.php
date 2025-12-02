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
        'resources/js/index.js',
    ])
</head>
<body class="smooth-scroll">
    <!-- Animated particles background -->
    <div class="particles" id="particles"></div>

    <!-- Botón hamburguesa para navbar (móvil) -->
    <button class="mobile-navbar-btn" onclick="toggleMobileNavbar()" id="mobile-navbar-btn">
        <div class="hamburger-navbar">
            <span></span>
            <span></span>
            <span></span>
        </div>
    </button>

    @if (Auth::check())
        <!-- Include the navbar partial if the user is authenticated -->
        @include('partials.navbar')
    @endif
    <!-- Header -->


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
                    @if (Auth::check())
                        <a href="{{ route('profile.show') }}" class="sphere-btn">Ir a mi perfil</a>
                    @else
                        <a href="{{ route('login') }}" class="sphere-btn">Iniciar Sesión</a>
                    @endif

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

    <!-- Global vars and functions -->
    @include('partials.global-vars')

    <!-- Modern Mobile Navbar -->
    @include('partials.mobile-navbar')

</body>
</html>
