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

        <div class="api-info">
            <p class="api-text">
                Consulta la documentación para conocer todos los endpoints disponibles y cómo integrarlos con tus
                aplicaciones.
            </p>
        </div>
    </div>
</div>
