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
                            <a href="#auth">Autenticación</a>
                            <a href="#meetings">Reuniones</a>
                            <a href="#tasks">Tareas</a>
                            <a href="#users">Usuarios</a>
                            <a href="#errors">Errores comunes</a>
                            <a href="#integration">Integración en tu sistema</a>
                        </nav>
                        <div>
                            <a class="btn btn-outline" href="{{ route('profile.show') }}">Volver al perfil</a>
                        </div>
                    </aside>

                    <section class="doc-content">
                        <section class="doc-card" aria-labelledby="auth-title" id="auth">
                            <h2 id="auth-title">🔐 Autenticación</h2>
                            <p>
                                Crea o recupera tu token personal directamente desde tu perfil o emítelo programáticamente. El token
                                se devuelve con su fecha de expiración para que puedas renovarlo cuando sea necesario.
                            </p>
                            <div class="doc-grid columns-2 doc-api-examples" id="doc-api-section">
                                <article>
                                    <h3>Login con credenciales</h3>
                                    <p>Solicita un token firmado enviando las credenciales del usuario que integrará Juntify.</p>
                                    <div class="code-block">
<pre><code>POST {{ url('/api/integrations/login') }}
Content-Type: application/json

{
  "email": "usuario@empresa.com",
  "password": "********"
}</code></pre>
                                    </div>
                                    <p>Respuesta exitosa:</p>
                                    <div class="code-block">
<pre><code>{
  "token": "token_de_api",
  "expires_at": "2024-05-11T00:00:00Z"
}</code></pre>
                                    </div>
                                </article>
                                <article>
                                    <h3>Token desde la sesión activa</h3>
                                    <p>Cuando ya estás autenticado en el navegador puedes emitir un token sin volver a pedir contraseña.</p>
                                    <div class="code-block">
<pre><code>POST {{ url('/api/integrations/token') }}
# o GET {{ url('/api/integrations/token') }} si ya tienes la sesión activa
# CSRF solo necesario para POST
X-CSRF-TOKEN: {{ csrf_token() }}</code></pre>
                                    </div>
                                    <p class="mt-4">Incluye el encabezado <code>Authorization: Bearer &lt;token&gt;</code> en cada petición y utiliza <code>POST {{ url('/api/integrations/logout') }}</code> para revocar el token cuando dejes de usarlo.</p>
                                </article>
                            </div>
                        </section>

                        <section class="doc-card" id="meetings">
                            <h2>🧭 Reuniones</h2>
                            <p>
                                La API entrega metadatos, el contenido desencriptado del archivo <code>.ju</code> y la información
                                necesaria para reproducir el audio sin acceder a Google Drive. Todos los endpoints requieren el token
                                del usuario autenticado.
                            </p>
                            <div class="doc-grid">
                                <article class="code-block">
                                    <h3>Últimas reuniones</h3>
<pre><code>GET {{ url('/api/integrations/meetings') }}
Authorization: Bearer {{ '{token}' }}

{
  "data": [
    {
      "id": 1234,
      "title": "Demo con cliente",
      "created_at": "2024-05-10T17:00:12Z",
      "created_at_readable": "10/05/2024 14:00"
    }
  ]
}</code></pre>
                                </article>
                                <article class="code-block">
                                    <h3>Detalle con archivo .ju y audio</h3>
<pre><code>GET {{ url('/api/integrations/meetings/{meeting}') }}
Authorization: Bearer {{ '{token}' }}

{
  "data": {
    "id": 1234,
    "title": "Demo con cliente",
    "ju": {
      "available": true,
      "summary": "Resumen de la reunión...",
      "key_points": ["Punto 1", "Punto 2"],
      "tasks": [{"title": "Enviar propuesta", "owner": "María"}],
      "transcription": "Texto completo..."
    },
    "audio": {
      "available": true,
      "filename": "demo_cliente.mp3",
      "mime_type": "audio/mpeg",
      "stream_url": "{{ url('/api/integrations/meetings/1234/audio') }}"
    }
  }
}</code></pre>
                                    <p class="mt-4">El campo <code>stream_url</code> ya está autenticado y permite reproducir el audio directamente en tu panel.</p>
                                </article>
                                <article class="code-block">
                                    <h3>Streaming de audio</h3>
<pre><code>GET {{ url('/api/integrations/meetings/{meeting}/audio') }}
Authorization: Bearer {{ '{token}' }}
Accept: audio/*

// Devuelve el flujo binario del archivo original</code></pre>
                                </article>
                                <article class="code-block">
                                    <h3>Tareas de una reunión</h3>
<pre><code>GET {{ url('/api/integrations/meetings/{meeting}/tasks') }}
Authorization: Bearer {{ '{token}' }}

{
  "meeting": {
    "id": 1234,
    "title": "Demo con cliente"
  },
  "tasks": [
    {
      "id": 777,
      "title": "Enviar propuesta",
      "status": "pendiente",
      "due_date": "2024-05-12",
      "due_time": "18:00"
    }
  ]
}</code></pre>
                                </article>
                            </div>
                        </section>

                        <section class="doc-card" id="tasks">
                            <h2>🗂️ Tareas asignadas</h2>
                            <p>
                                Consulta y filtra todas las tareas asociadas al usuario autenticado. Puedes combinar los resultados con la
                                información de reuniones para construir paneles de seguimiento.
                            </p>
                            <div class="code-block">
<pre><code>GET {{ url('/api/integrations/tasks') }}
Authorization: Bearer {{ '{token}' }}

Parámetros opcionales:
- meeting_id: Filtra tareas de una reunión específica.

Respuesta 200:
{
  "data": [
    {
      "id": 777,
      "title": "Enviar propuesta",
      "status": "en_progreso",
      "progress": 50,
      "due_date": "2024-05-12",
      "due_time": "18:00",
      "assigned_to": "María",
      "meeting": {
        "id": 1234,
        "title": "Demo con cliente"
      }
    }
  ]
}</code></pre>
                            </div>
                        </section>

                        <section class="doc-card" id="users">
                            <h2>🙋 Búsqueda de usuarios</h2>
                            <p>Localiza miembros de tu organización para asignaciones o validaciones dentro de tu panel externo.</p>
                            <div class="code-block">
<pre><code>GET {{ url('/api/integrations/users/search?query=mar') }}
Authorization: Bearer {{ '{token}' }}

{
  "data": [
    {
      "id": 55,
      "full_name": "María García",
      "email": "maria@empresa.com",
      "role": "manager"
    }
  ]
}</code></pre>
                            </div>
                        </section>

                        <section class="doc-card" id="errors">
                            <h2>⚠️ Errores comunes</h2>
                            <ul class="list-disc list-inside space-y-2 text-sm text-slate-300">
                                <li><code>401 Unauthorized</code>: el token es inválido o expiró. Renueva el token o ejecuta <code>/login</code> nuevamente.</li>
                                <li><code>404 Not Found</code>: el recurso no pertenece al usuario autenticado o ya no está disponible.</li>
                                <li><code>429 Too Many Requests</code>: se alcanzó el límite de peticiones. Implementa reintentos con backoff exponencial.</li>
                            </ul>
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

  async function login(email, password, deviceName = 'Mi integración') {
    const response = await fetch(`${baseUrl}/login`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      credentials: 'include',
      body: JSON.stringify({ email, password, device_name: deviceName })
    });
    if (!response.ok) throw new Error('Credenciales inválidas');
    const { token } = await response.json();
    localStorage.setItem('juntifyApiToken', token);
    return token;
  }

  async function tokenFromSession(deviceName = 'Mi integración') {
    try {
      const response = await fetch(`${baseUrl}/token`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'include',
        body: JSON.stringify({ device_name: deviceName })
      });

      if (!response.ok) {
        const details = await response.text();
        throw new Error(`No se pudo obtener el token desde la sesión (${response.status}). ${details}`.trim());
      }

      const { token } = await response.json();
      localStorage.setItem('juntifyApiToken', token);
      return token;
    } catch (error) {
      console.error('Token from session error:', error);
      throw error;
    }
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
  // const sessionToken = await tokenFromSession();
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
