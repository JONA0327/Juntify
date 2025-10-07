<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Documentación API - Juntify</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700&display=swap" rel="stylesheet" />

    @vite([
        'resources/css/app.css',
        'resources/js/app.js',
        'resources/css/index.css',
        'resources/js/index.js',
        'resources/css/profile-documentation.css',
        'resources/js/profile-documentation.js'
    ])
</head>
<body class="bg-slate-950 text-slate-200 font-sans antialiased">
    <div class="flex">
        @include('partials.navbar')
        @include('partials.mobile-nav')

        <main class="w-full pt-20 lg:pl-24 lg:pt-24">
            <div class="doc-container">
                <header class="doc-header" id="overview">
                    <div class="doc-mobile-header">
                        <h1>API de Juntify</h1>
                        <button type="button" data-doc-toggle>
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="22" height="22">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5" />
                            </svg>
                        </button>
                    </div>
                    <h1>Integra Juntify con tus aplicaciones</h1>
                    <p>
                        Genera y administra tu API Key personal, conecta tus aplicaciones externas y aprovecha los endpoints
                        protegidos de Juntify. Sigue la guía paso a paso para autenticarte, consumir información y presentar
                        datos directamente en tus propios paneles.
                    </p>
                </header>

                <div class="doc-layout">
                    <div class="doc-overlay"></div>

                    <aside class="doc-sidebar">
                        <h2>Contenido</h2>
                        <nav>
                            <a href="#overview" class="active">Introducción</a>
                            <a href="#api-key">Uso de API Key</a>
                            <a href="#endpoints">Endpoints disponibles</a>
                            <a href="#integration">Integración en tu sistema</a>
                        </nav>
                        <div>
                            <a class="btn btn-outline" href="{{ route('profile.show') }}">Volver al perfil</a>
                        </div>
                    </aside>

                    <section class="doc-content">
                        <section class="doc-card" aria-labelledby="api-key-title" id="api-key">
                            <h2 id="api-key-title">🔐 Uso de API Key</h2>
                            <p>
                                Con una sesión activa en Juntify puedes generar tu token personal directamente desde tu perfil. El token
                                queda disponible para probar los endpoints desde esta documentación y puedes revocarlo cuando lo necesites.
                            </p>

                            <div class="doc-grid columns-3 doc-api-examples" id="doc-api-section">
                                <article>
                                    <h3>Generar y custodiar tu token</h3>
                                    <p>
                                        Conserva tu token seguro y revócalo cuando ya no lo necesites. Si debes emitirlo desde otra aplicación,
                                        realiza una petición al endpoint de autenticación con las credenciales del usuario que integrará Juntify.
                                    </p>
                                    <div class="code-block">
                                        <pre><code>curl -X POST {{ url('/api/integrations/login') }} \
  -H "Content-Type: application/json" \
  -d '{"email":"tu-correo@empresa.com","password":"tu-contraseña"}'</code></pre>
                                    </div>
                                </article>

                                <article>
                                    <h3>Consumir la API</h3>
                                    <p>Envía el token en el encabezado <code>Authorization: Bearer &lt;token&gt;</code> para acceder a tus recursos.</p>
                                    <div class="code-block">
                                        <pre><code>fetch('{{ url('/api/integrations/meetings') }}', {
  headers: {
    'Authorization': `Bearer ${token}`
  }
})
  .then(res => res.json())
  .then(console.log);</code></pre>
                                    </div>
                                </article>

                                <article>
                                    <h3>Buscar usuarios</h3>
                                    <p>Consulta información puntual de usuarios utilizando el endpoint de búsqueda.</p>
                                    <div class="code-block">
                                        <pre><code>GET {{ url('/api/integrations/users/search?query=ana') }}</code></pre>
                                    </div>
                                </article>
                            </div>
                        </section>

                        <section class="doc-card" id="endpoints">
                            <h2>🧭 Endpoints disponibles</h2>
                            <p>
                                Todos los endpoints siguen el prefijo <code>/api/integrations</code> y responden en formato JSON.
                                Recuerda incluir el encabezado <code>Authorization</code> en cada solicitud.
                            </p>
                            <div class="doc-grid">
                                <article class="code-block">
                                    <h3>Reuniones</h3>
                                    <pre><code>GET {{ url('/api/integrations/meetings') }}
Respuesta: {
  "data": [
    {
      "id": 1,
      "title": "Daily Standup",
      "created_at_readable": "2024-05-04 09:00"
    }
  ]
}</code></pre>
                                </article>
                                <article class="code-block">
                                    <h3>Tareas</h3>
                                    <pre><code>GET {{ url('/api/integrations/tasks') }}
GET {{ url('/api/integrations/tasks?meeting_id=123') }}</code></pre>
                                </article>
                                <article class="code-block">
                                    <h3>Búsqueda de usuarios</h3>
                                    <pre><code>GET {{ url('/api/integrations/users/search?query=ana') }}</code></pre>
                                </article>
                            </div>
                        </section>

                        <section class="doc-card" id="integration">
                            <h2>🧩 Integración en tu sistema</h2>
                            <p>
                                Inserta el siguiente fragmento de código en tu página web para conectar con la API de Juntify.
                                El componente se encarga de pedir tus credenciales, guardar el token y mostrar reuniones y tareas.
                            </p>
                            <div class="code-block">
                                <button type="button" class="btn btn-secondary" id="copy-doc-snippet">Copiar fragmento</button>
<pre id="doc-snippet-content"><code>&lt;!-- Contenedor Juntify API --&gt;
&lt;div id="juntify-api-widget" data-endpoint="{{ url('/api/integrations') }}"&gt;&lt;/div&gt;
&lt;script type="module"&gt;
  const container = document.getElementById('juntify-api-widget');
  const baseUrl = container.dataset.endpoint;

  async function login(email, password) {
    const response = await fetch(`${baseUrl}/login`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email, password, device_name: 'Mi integración' })
    });
    if (!response.ok) throw new Error('Credenciales inválidas');
    const { token } = await response.json();
    localStorage.setItem('juntifyApiToken', token);
    return token;
  }

  async function request(path) {
    const token = localStorage.getItem('juntifyApiToken');
    if (!token) throw new Error('No hay token disponible');
    const response = await fetch(`${baseUrl}/${path}`, {
      headers: { Authorization: `Bearer ${token}` }
    });
    if (response.status === 401) {
      localStorage.removeItem('juntifyApiToken');
      throw new Error('Token expirado, vuelve a iniciar sesión');
    }
    return response.json();
  }

  // Ejemplo de uso
  // const token = await login('tu-correo@empresa.com', 'tu-contraseña');
  // const meetings = await request('meetings');
&lt;/script&gt;</code></pre>
                            </div>
                            <p>
                                Puedes extender este widget para mostrar listas personalizadas, sincronizar tareas o disparar flujos
                                en tu CRM. Solo necesitas manejar el almacenamiento del token y reutilizarlo en cada llamada.
                            </p>
                        </section>
                    </section>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
