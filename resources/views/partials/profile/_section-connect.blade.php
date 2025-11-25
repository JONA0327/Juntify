<!-- Sección: Conectar Servicios -->
<div class="content-section" id="section-connect" style="display: none;" data-tutorial="connect-section">
    <div class="content-grid">
        @if(!$driveConnected)
            <!-- No conectado -->
            <div class="info-card" data-tutorial="connect-card">
                <h3 class="card-title">
                    <span style="display: flex; align-items: center; gap: 0.5rem;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12.545 10.239v3.821h5.445c-.712 2.315-2.647 3.972-5.445 3.972-3.332 0-6.033-2.701-6.033-6.032s2.701-6.032 6.033-6.032c1.498 0 2.866.549 3.921 1.453l2.814-2.814C17.503 2.988 15.139 2 12.545 2 7.021 2 2.543 6.477 2.543 12s4.478 10 10.002 10c8.396 0 10.249-7.85 9.426-11.748L12.545 10.239z" fill="#4285F4"/>
                        </svg>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM7 10h5v5H7z" fill="#34A853"/>
                        </svg>
                        Drive y Calendar
                    </span>
                </h3>
                <div class="info-item">
                    <span class="info-label">Estado</span>
                    <span class="status-badge google-connection-status" style="background: rgba(239, 68, 68, 0.2); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3);">
                        Desconectado
                    </span>
                    <div class="google-connection-indicator" style="display: none; margin-left: 10px;">
                        <svg class="google-refresh-spinner" width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="animation: spin 1s linear infinite;">
                            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" fill="none" stroke-dasharray="31.416" stroke-dashoffset="31.416" opacity="0.3"/>
                            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" fill="none" stroke-dasharray="31.416" stroke-dashoffset="23.562"/>
                        </svg>
                    </div>
                </div>
                <div class="info-item">
                    @if($driveLocked)
                        <span class="info-value">Tu plan actual no permite conectar Google Drive. Las reuniones nuevas se guardarán temporalmente durante {{ $tempRetentionDays }} {{ $tempRetentionDays === 1 ? 'día' : 'días' }}.</span>
                    @else
                        <span class="info-value">Debes reconectar tu cuenta de Google.</span>
                    @endif
                </div>
                <div class="action-buttons" data-tutorial="connect-actions">
                    @if($driveLocked)
                        <button type="button" class="btn btn-secondary" id="connect-drive-btn" data-drive-locked="true" data-tutorial="connect-drive-button">
                            🔒 Disponible en planes Business y Enterprise
                        </button>
                    @else
                        <button type="button" class="btn btn-primary" id="connect-drive-btn" data-drive-locked="false" data-tutorial="connect-drive-button">
                            🔗 Conectar Drive y Calendar
                        </button>
                    @endif
                </div>
            </div>
        @else
            <!-- Conectado - Estado de Drive -->
            <div class="info-card" data-tutorial="connect-card">
                <h3 class="card-title">
                    <span style="display: flex; align-items: center; gap: 0.5rem;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12.545 10.239v3.821h5.445c-.712 2.315-2.647 3.972-5.445 3.972-3.332 0-6.033-2.701-6.033-6.032s2.701-6.032 6.033-6.032c1.498 0 2.866.549 3.921 1.453l2.814-2.814C17.503 2.988 15.139 2 12.545 2 7.021 2 2.543 6.477 2.543 12s4.478 10 10.002 10c8.396 0 10.249-7.85 9.426-11.748L12.545 10.239z" fill="#4285F4"/>
                        </svg>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM7 10h5v5H7z" fill="#34A853"/>
                        </svg>
                        Drive y Calendar
                    </span>
                </h3>
                <div class="info-item">
                    <span class="info-label">Drive</span>
                    <span class="status-badge status-active google-drive-status">Conectado</span>
                    <div class="google-connection-indicator" style="display: none; margin-left: 10px;">
                        <svg class="google-refresh-spinner" width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="animation: spin 1s linear infinite;">
                            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" fill="none" stroke-dasharray="31.416" stroke-dashoffset="31.416" opacity="0.3"/>
                            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" fill="none" stroke-dasharray="31.416" stroke-dashoffset="23.562"/>
                        </svg>
                    </div>
                </div>
                <div class="info-item">
                    <span class="info-label">Calendar</span>
                    <span id="calendar-status" class="status-badge google-calendar-status {{ $calendarConnected ? 'status-active' : '' }}" @unless($calendarConnected) style="background: rgba(239, 68, 68, 0.2); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3);" @endunless>
                        {{ $calendarConnected ? 'Conectado' : 'Sin acceso' }}
                    </span>
                    <div class="google-connection-indicator" style="display: none; margin-left: 10px;">
                        <svg class="google-refresh-spinner" width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="animation: spin 1s linear infinite;">
                            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" fill="none" stroke-dasharray="31.416" stroke-dashoffset="31.416" opacity="0.3"/>
                            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" fill="none" stroke-dasharray="31.416" stroke-dashoffset="23.562"/>
                        </svg>
                    </div>
                </div>
                <div class="info-item" id="calendar-advice" @if($calendarConnected) style="display:none;" @endif>
                    <span class="info-value">Vuelve a conectar a través de <a href="{{ route('google.reauth') }}" style="text-decoration: underline;">Google OAuth</a>.</span>
                </div>
                @if($lastSync)
                <div class="info-item">
                    <span class="info-label">Última sincronización</span>
                    <span class="info-value">{{ $lastSync->format('d/m/Y H:i:s') }}</span>
                </div>
                @endif
                @if($driveLocked)
                <div class="info-item">
                    <span class="info-value" style="color: #f97316;">
                        Tu plan actual ya no permite nuevas conexiones de Drive. Las reuniones recientes se guardarán temporalmente durante {{ $tempRetentionDays }} {{ $tempRetentionDays === 1 ? 'día' : 'días' }} hasta que actualices tu plan.
                    </span>
                </div>
                @endif
                <div class="action-buttons" data-tutorial="connect-actions">
                    <form method="POST" action="{{ route('drive.disconnect') }}">
                        @csrf
                        <button type="submit" class="btn btn-secondary">
                            🔌 Cerrar sesión de Drive y Calendar
                        </button>
                    </form>
                </div>
            </div>

            <!-- Configuración de Carpetas -->
            <div class="info-card" data-tutorial="folder-config-card">
                <h3 class="card-title">
                    <span class="card-icon">📁</span>
                    Configuración de Carpetas
                </h3>

                <div style="margin-bottom: 1.5rem;">
                    <label class="form-label">Carpeta Principal</label>
                    @if(!empty($folderMessage))
                        <div class="info-item">
                            <span class="info-value" style="color: #ef4444;">{{ $folderMessage }}</span>
                        </div>
                    @endif
                    <div class="info-item">
                        <span class="info-value" style="color:#cbd5e1;">
                            Juntify ya establece una carpeta automática en tu Google Drive. Si prefieres usar otra como principal, pega aquí su ID y haz clic en "Establecer Carpeta".
                        </span>
                    </div>
                    @if($folder)
                        <div style="background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.2); border-radius: 8px; padding: 1rem; margin-bottom: 1rem;">
                            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem;">
                                <span style="font-size: 1.2rem;">📁</span>
                                <div id="main-folder-name" data-name="{{ $folder->name ?? '' }}" data-id="{{ $folder->google_id ?? '' }}" style="flex: 1; min-width: 0;">
                                    <div style="color: #ffffff; font-weight: 600; word-break: break-all;">
                                        {{ $folder->name }}
                                    </div>
                                    <div style="color: #94a3b8; font-size: 0.8rem; font-family: monospace; word-break: break-all;">
                                        ID: {{ $folder->google_id }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    <input
                        type="text"
                        class="form-input"
                        id="main-folder-input"
                        placeholder="ID de la carpeta principal"
                        data-id="{{ $folder->google_id ?? '' }}"
                        style="margin-bottom: 1rem;"
                    >

                    <div class="action-buttons" data-tutorial="folder-actions">
                        <button class="btn btn-secondary" id="set-main-folder-btn">
                            ✅ Establecer Carpeta
                        </button>
                    </div>
                </div>
            </div>

            <!-- Subcarpetas -->
            @if($folder)
            <div class="info-card" id="subfolder-card" data-tutorial="subfolder-card">
                <h3 class="card-title">
                    <span class="card-icon">📂</span>
                    Estructura de Carpetas
                </h3>
                <div style="margin-bottom: 1.5rem; line-height:1.5; color:#cbd5e1; font-size:0.9rem;">
                    Ahora la organización es automática. Al trabajar con tus reuniones se crearán (si no existen) estas carpetas dentro de tu carpeta principal:
                    <ul style="margin:0.75rem 0 0 1.2rem; list-style:disc;">
                        <li><strong>Audios</strong>: Archivos de audio finales</li>
                        <li><strong>Transcripciones</strong>: Archivos .ju encriptados con la transcripción y resumen</li>
                        <li><strong>Audios Pospuestos</strong>: Audios subidos pendientes de completar</li>
                        <li><strong>Documentos</strong>: Exportaciones y archivos adicionales relacionados a la reunión</li>
                    </ul>
                    No necesitas crear subcarpetas manualmente. Esta sección reemplaza la antigua gestión de subcarpetas.
                </div>
            </div>
            @endif
        @endif
    </div>
</div>
