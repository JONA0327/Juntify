<div class="content-section" id="section-apikey" style="display: none;">
    <div class="info-card api-card">
        <h2 class="card-title">
            <span class="card-icon">🔐</span>
            API de Juntify
        </h2>
        <p class="api-intro">
            Integra tus aplicaciones con Juntify para consultar reuniones, tareas y usuarios de forma segura.
            Genera tu token personal desde este panel y reutilízalo en tus integraciones externas.
        </p>

        <div class="api-docs-cta">
            <a href="{{ route('profile.documentation') }}" class="btn btn-outline">Ver documentación detallada</a>
        </div>

        <div class="api-status-panel">
            <div>
                <span id="api-connection-status" class="api-status-badge api-status--disconnected">Sin token activo</span>
                <p class="api-status-help">Con tu sesión iniciada puedes generar un token y habilitar las consultas desde este panel.</p>
            </div>
            <div class="api-token-wrapper">
                <span class="api-token-label">Token activo</span>
                <code id="api-token-value" class="api-token-value">Aún no has generado un token desde este dispositivo.</code>
                <div class="api-token-actions">
                    <button type="button" class="btn btn-primary" id="api-generate-token">Generar token</button>
                    <button type="button" class="btn btn-secondary" id="api-copy-token" disabled>Copiar token</button>
                    <button type="button" class="btn btn-danger" id="api-logout-btn" disabled>Revocar token</button>
                </div>
            </div>
        </div>

        <div class="api-sections-grid">
            <section class="api-section">
                <h3>Gestiona tu token</h3>
                <p class="api-text">
                    Estando autenticado en Juntify puedes generar un token personal con un clic. El token se almacena de forma
                    local para que puedas probar los endpoints y revocarlo cuando ya no lo necesites.
                </p>
                <p class="api-text">
                    Si necesitas crear tokens desde otra aplicación (por ejemplo, tu propio panel), utiliza el endpoint de
                    autenticación descrito en la documentación.
                </p>
                <div class="api-snippet">
                    <span class="api-snippet-label">Generar token vía API</span>
                    <pre><code>curl -X POST https://tuservidor.com/api/integrations/login \&#10;  -H "Content-Type: application/json" \&#10;  -d '{"email":"tu-correo@empresa.com","password":"tu-contraseña"}'</code></pre>
                </div>
            </section>

            <section class="api-section">
                <h3>Consume la API</h3>
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
                <h3>Pruebas rápidas</h3>
                <p class="api-text">Cuando generes tu token podrás visualizar tus reuniones y tareas recientes directamente desde este panel.</p>
                <div id="api-data-panels" class="api-data-panels" style="display: none;">
                    <div class="api-data-card">
                        <h4>Reuniones recientes</h4>
                        <ul id="api-meetings-list" class="api-list">
                            <li class="api-list-empty">Genera tu token para ver tus reuniones.</li>
                        </ul>
                    </div>
                    <div class="api-data-card">
                        <h4>Tareas asociadas</h4>
                        <ul id="api-tasks-list" class="api-list">
                            <li class="api-list-empty">Genera tu token para listar tus tareas.</li>
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
