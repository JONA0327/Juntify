<div class="content-section" id="section-apikey" style="display: none;">
    <div class="info-card api-card">
        <h2 class="card-title">
            <span class="card-icon">🔐</span>
            API de Juntify
        </h2>
        <p class="api-intro">
            Integra tus aplicaciones con Juntify para consultar reuniones, tareas y usuarios de forma segura.
            Sigue la guía paso a paso para autenticarte, guardar tu token y consumir los endpoints disponibles.
        </p>

        <div class="api-status-panel">
            <div>
                <span id="api-connection-status" class="api-status-badge api-status--disconnected">Sin conectar</span>
                <p class="api-status-help">Inicia sesión para generar un token y habilitar las consultas desde este panel.</p>
            </div>
            <div class="api-token-wrapper">
                <span class="api-token-label">Token activo</span>
                <code id="api-token-value" class="api-token-value">No has iniciado sesión aún.</code>
                <div class="api-token-actions">
                    <button type="button" class="btn btn-secondary" id="api-copy-token" disabled>Copiar token</button>
                    <button type="button" class="btn btn-danger" id="api-logout-btn" disabled>Revocar token</button>
                </div>
            </div>
        </div>

        <div class="api-sections-grid">
            <section class="api-section">
                <h3>Paso 1: Autenticación</h3>
                <p class="api-text">Utiliza tus credenciales de Juntify para generar un token personal. Puedes hacerlo desde este formulario o vía API.</p>
                <form id="api-login-form" class="api-form" autocomplete="off">
                    <div class="form-row">
                        <label for="api-login-email">Correo electrónico</label>
                        <input type="email" id="api-login-email" name="email" placeholder="tu-correo@empresa.com" required>
                    </div>
                    <div class="form-row">
                        <label for="api-login-password">Contraseña</label>
                        <input type="password" id="api-login-password" name="password" placeholder="••••••••" required>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary" id="api-login-submit">Generar token</button>
                    </div>
                </form>
                <div class="api-snippet">
                    <span class="api-snippet-label">Ejemplo con cURL</span>
                    <pre><code>curl -X POST https://tuservidor.com/api/integrations/login \&#10;  -H "Content-Type: application/json" \&#10;  -d '{"email":"tu-correo@empresa.com","password":"tu-contraseña"}'</code></pre>
                </div>
            </section>

            <section class="api-section">
                <h3>Paso 2: Consumir la API</h3>
                <p class="api-text">Incluye el token en el encabezado <code>Authorization: Bearer &lt;token&gt;</code> para acceder a los recursos.</p>
                <div class="api-endpoints">
                    <article class="api-endpoint">
                        <div class="api-endpoint-header">
                            <span class="api-method get">GET</span>
                            <span class="api-path">/api/integrations/meetings</span>
                        </div>
                        <p class="api-endpoint-description">Lista las últimas reuniones creadas por el usuario autenticado.</p>
                        <pre><code>curl https://tuservidor.com/api/integrations/meetings \&#10;  -H "Authorization: Bearer &lt;token&gt;"</code></pre>
                    </article>
                    <article class="api-endpoint">
                        <div class="api-endpoint-header">
                            <span class="api-method get">GET</span>
                            <span class="api-path">/api/integrations/tasks</span>
                        </div>
                        <p class="api-endpoint-description">Devuelve tus tareas recientes y las asociadas a tus reuniones.</p>
                        <pre><code>curl "https://tuservidor.com/api/integrations/tasks?meeting_id=123" \&#10;  -H "Authorization: Bearer &lt;token&gt;"</code></pre>
                    </article>
                    <article class="api-endpoint">
                        <div class="api-endpoint-header">
                            <span class="api-method get">GET</span>
                            <span class="api-path">/api/integrations/users/search</span>
                        </div>
                        <p class="api-endpoint-description">Busca usuarios de Juntify por nombre, correo o username.</p>
                        <pre><code>curl "https://tuservidor.com/api/integrations/users/search?query=mar" \&#10;  -H "Authorization: Bearer &lt;token&gt;"</code></pre>
                    </article>
                </div>
            </section>

            <section class="api-section">
                <h3>Paso 3: Consultas desde el panel</h3>
                <p class="api-text">Una vez que hayas iniciado sesión, podrás visualizar rápidamente tus reuniones y tareas, además de buscar usuarios.</p>
                <div id="api-data-panels" class="api-data-panels" style="display: none;">
                    <div class="api-data-card">
                        <h4>Reuniones recientes</h4>
                        <ul id="api-meetings-list" class="api-list">
                            <li class="api-list-empty">Inicia sesión para ver tus reuniones.</li>
                        </ul>
                    </div>
                    <div class="api-data-card">
                        <h4>Tareas asociadas</h4>
                        <ul id="api-tasks-list" class="api-list">
                            <li class="api-list-empty">Inicia sesión para listar tus tareas.</li>
                        </ul>
                    </div>
                </div>

                <form id="api-user-search-form" class="api-form api-form-inline">
                    <div class="form-row">
                        <label for="api-user-search-input">Buscar usuarios</label>
                        <input type="text" id="api-user-search-input" name="query" placeholder="Escribe al menos 2 caracteres" minlength="2">
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-secondary">Buscar</button>
                    </div>
                </form>
                <ul id="api-user-search-results" class="api-list"></ul>
            </section>
        </div>
    </div>
</div>
