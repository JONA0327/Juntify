<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Gestión de Analizadores - Juntify</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700&display=swap" rel="stylesheet" />

    @vite([
        'resources/css/app.css',
        'resources/js/app.js',
        'resources/css/index.css',
        'resources/css/profile.css',
        'resources/js/profile.js'
    ])
</head>
<body>
    <div class="particles" id="particles"></div>

    @include('partials.navbar')

    <div class="mobile-bottom-nav">
        <div class="nav-item">
            <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v1.5M17.25 3v1.5M3.75 7.5h16.5M21 6.75A2.25 2.25 0 0018.75 4.5H5.25A2.25 2.25 0 003 6.75v12A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V6.75z" />
            </svg>
            <span class="nav-label">Reuniones</span>
        </div>
        <div class="nav-item">
            <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <rect x="5" y="7" width="14" height="10" rx="2" stroke-linecap="round" stroke-linejoin="round"/>
                <circle cx="9" cy="12" r="1" fill="currentColor"/>
                <circle cx="15" cy="12" r="1" fill="currentColor"/>
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 7V4m-6 6H4m16 0h-2" />
            </svg>
            <span class="nav-label">Asistente IA</span>
        </div>
        <div class="nav-item nav-center">
            <svg class="nav-icon-center" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
        </div>
        <div class="nav-item">
            <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2.25 2.25L15 10.5m6 1.5a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span class="nav-label">Tareas</span>
        </div>
        <div class="nav-item dropdown-trigger" onclick="toggleMobileDropdown()">
            <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 12a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm7.5 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm7.5 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" />
            </svg>
            <span class="nav-label">Más</span>
            <div class="mobile-dropdown" id="mobile-dropdown">
                <a href="{{ route('profile.show') }}" class="dropdown-item">
                    <svg class="dropdown-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9A3.75 3.75 0 1112 5.25 3.75 3.75 0 0115.75 9zM18 21H6a2.25 2.25 0 01-2.25-2.25v-1.5a2.25 2.25 0 012.25-2.25h12a2.25 2.25 0 012.25 2.25v1.5A2.25 2.25 0 0118 21z" />
                    </svg>
                    <span class="dropdown-text">Perfil</span>
                </a>
                <a href="/admin" class="dropdown-item">
                    <svg class="dropdown-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span class="dropdown-text">Admin</span>
                </a>
            </div>
        </div>
    </div>

    <div class="mobile-dropdown-overlay" id="mobile-dropdown-overlay" onclick="closeMobileDropdown()"></div>

    <div class="app-container">
        <main class="main-admin">
            <div class="content-header">
                <div>
                    <h1 class="page-title">Gestión de Analizadores</h1>
                    <p class="page-subtitle">Crea y administra los analizadores de IA para el procesamiento de reuniones</p>
                </div>
                <div class="action-buttons">
                    <button class="btn btn-secondary" onclick="window.location.href='/admin'">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                        </svg>
                        Volver al panel
                    </button>
                    <button class="btn btn-primary" onclick="showCreateAnalyzerModal()">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        Crear Analizador
                    </button>
                </div>
            </div>

            <div class="content-grid">
                @forelse ($analyzers as $analyzer)
                    <div class="info-card analyzer-card" data-analyzer-id="{{ $analyzer->id }}">
                        <div class="analyzer-header">
                            <h3 class="card-title">
                                <span class="card-icon">{{ $analyzer->icon }}</span>
                                {{ $analyzer->name }}
                            </h3>
                            <div class="analyzer-actions">
                                <button class="control-btn" onclick="editAnalyzer('{{ $analyzer->id }}')" title="Editar analizador">
                                    <svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 3.487l3.651 3.651-9.375 9.375-3.651.975.975-3.651 9.4-9.35zM5.25 18.75h13.5" />
                                    </svg>
                                </button>
                                <button class="control-btn delete-btn" onclick="deleteAnalyzer('{{ $analyzer->id }}')" title="Eliminar analizador">
                                    <svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <div class="analyzer-description">
                            <p>{{ $analyzer->description }}</p>
                        </div>
                        <div class="analyzer-details">
                            <div class="info-item">
                                <span class="info-label">Tipo</span>
                                <span class="status-badge {{ $analyzer->is_system ? 'status-active' : 'status-inactive' }}">{{ $analyzer->is_system ? 'Sistema' : 'Personalizado' }}</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Creado</span>
                                <span class="info-value">{{ $analyzer->created_at->format('d/m/Y') }}</span>
                            </div>
                        </div>
                    </div>
                @empty
                    <p>No se encontraron analizadores</p>
                @endforelse
            </div>
        </main>
    </div>

    <div class="modal" id="analyzer-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="modal-title">
                    <svg class="modal-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 3.104v5.714a2.25 2.25 0 01-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 014.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15.3M14.25 3.104c.251.023.501.05.75.082M19.8 15.3l-1.57.393A9.065 9.065 0 0112 15a9.065 9.065 0 00-6.23-.693L5 14.5m14.8.8l1.402 1.402c.232.232.348.694.348 1.154v1.697a2.25 2.25 0 01-2.25 2.25H5.25a2.25 2.25 0 01-2.25-2.25v-1.697c0-.46.116-.922.348-1.154L5 14.5" />
                    </svg>
                    Crear Nuevo Analizador
                </h3>
            </div>
            <div class="modal-body">
                <form id="analyzer-form">
                    <div class="form-group">
                        <label class="form-label">Nombre del Analizador</label>
                        <input type="text" class="modal-input" id="analyzer-name" placeholder="Ej: Análisis de Estrategia" required>
                        <div class="input-hint">Nombre descriptivo que aparecerá en la lista de analizadores</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Descripción</label>
                        <textarea class="modal-input" id="analyzer-description" rows="3" placeholder="Describe qué tipo de análisis realizará este analizador..." required></textarea>
                        <div class="input-hint">Descripción breve de la funcionalidad del analizador</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Prompt del Sistema</label>
                        <textarea class="modal-input" id="analyzer-prompt" rows="8" placeholder="Eres un asistente especializado en... Tu función es analizar transcripciones de reuniones y..." required></textarea>
                        <div class="input-hint">Instrucciones detalladas para ChatGPT sobre cómo debe comportarse este analizador</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Prompt de Usuario</label>
                        <textarea class="modal-input" id="analyzer-user-prompt" rows="4" placeholder="Ej: Dime el resumen ejecutivo de la reunión" required></textarea>
                        <div class="input-hint">Plantilla de pregunta o texto que proporcionará el usuario</div>
                    </div>


                    <div class="form-group">
                        <label class="form-label">Tipo de Analizador</label>
                        <select class="modal-input" id="analyzer-type">
                            <option value="1">Sistema</option>
                            <option value="0">Personalizado</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Icono (Emoji)</label>
                        <input type="text" class="modal-input" id="analyzer-icon" placeholder="🧠" maxlength="2">
                        <div class="input-hint">Emoji que representará este analizador (opcional)</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeAnalyzerModal()">Cancelar</button>
                <button class="btn btn-primary" id="save-analyzer-btn" onclick="saveAnalyzer()">
                    <svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                    </svg>
                    Guardar Analizador
                </button>
            </div>
        </div>
    </div>

    <div class="modal" id="delete-analyzer-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">
                    <svg class="modal-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                    </svg>
                    Confirmar Eliminación
                </h3>
            </div>
            <div class="modal-body">
                <p class="modal-description">
                    ¿Estás seguro de que quieres eliminar este analizador? Esta acción no se puede deshacer.
                </p>
                <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 8px; padding: 1rem; margin-top: 1rem;">
                    <p style="color: #ef4444; font-size: 0.9rem; margin: 0;">
                        <strong>Advertencia:</strong> Eliminar este analizador afectará todas las futuras transcripciones que dependan de él.
                    </p>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeDeleteModal()">Cancelar</button>
                <button class="btn btn-danger" id="confirm-delete-btn" onclick="confirmDeleteAnalyzer()">
                    <svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                    </svg>
                    Eliminar Analizador
                </button>
            </div>
        </div>
    </div>

    <script>
        let editingAnalyzerId = null;
        let deletingAnalyzerId = null;

        function loadAnalyzers() {
            axios.get('/admin/analyzers/list')
                .then(res => {
                    const grid = document.querySelector('.content-grid');
                    grid.innerHTML = '';
                    if (res.data.length === 0) {
                        grid.innerHTML = '<p>No se encontraron analizadores</p>';
                        return;
                    }
                    res.data.forEach(analyzer => {
                        const badge = analyzer.is_system ? 'status-active' : 'status-inactive';
                        grid.insertAdjacentHTML('beforeend', `
                            <div class="info-card analyzer-card" data-analyzer-id="${analyzer.id}">
                                <div class="analyzer-header">
                                    <h3 class="card-title">
                                        <span class="card-icon">${analyzer.icon ?? ''}</span>
                                        ${analyzer.name}
                                    </h3>
                                    <div class="analyzer-actions">
                                        <button class="control-btn" onclick="editAnalyzer('${analyzer.id}')" title="Editar analizador">
                                            <svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 3.487l3.651 3.651-9.375 9.375-3.651.975.975-3.651 9.4-9.35zM5.25 18.75h13.5" />
                                            </svg>
                                        </button>
                                        <button class="control-btn delete-btn" onclick="deleteAnalyzer('${analyzer.id}')" title="Eliminar analizador">
                                            <svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                                <div class="analyzer-description">
                                    <p>${analyzer.description ?? ''}</p>
                                </div>
                                <div class="analyzer-details">
                                    <div class="info-item">
                                        <span class="info-label">Tipo</span>
                                        <span class="status-badge ${badge}">${analyzer.is_system ? 'Sistema' : 'Personalizado'}</span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Creado</span>
                                        <span class="info-value">${new Date(analyzer.created_at).toLocaleDateString('es-ES')}</span>
                                    </div>
                                </div>
                            </div>
                        `);
                    });
                })
                .catch(err => {
                    console.error('Error loading analyzers:', err);
                });
        }

        document.addEventListener('DOMContentLoaded', loadAnalyzers);

        function showCreateAnalyzerModal() {
            editingAnalyzerId = null;
            document.getElementById('modal-title').innerHTML = `
                <svg class="modal-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 3.104v5.714a2.25 2.25 0 01-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 014.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15.3M14.25 3.104c.251.023.501.05.75.082M19.8 15.3l-1.57.393A9.065 9.065 0 0112 15a9.065 9.065 0 00-6.23-.693L5 14.5m14.8.8l1.402 1.402c.232.232.348.694.348 1.154v1.697a2.25 2.25 0 01-2.25 2.25H5.25a2.25 2.25 0 01-2.25-2.25v-1.697c0-.46.116-.922.348-1.154L5 14.5" />
                </svg>
                Crear Nuevo Analizador
            `;
            document.getElementById('save-analyzer-btn').innerHTML = `
                <svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                </svg>
                Guardar Analizador
            `;

            // Limpiar formulario
            document.getElementById('analyzer-form').reset();

            document.getElementById('analyzer-modal').classList.add('show');
        }

        function editAnalyzer(analyzerId) {
            editingAnalyzerId = analyzerId;
            document.getElementById('modal-title').innerHTML = `
                <svg class="modal-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 3.487l3.651 3.651-9.375 9.375-3.651.975.975-3.651 9.4-9.35zM5.25 18.75h13.5" />
                </svg>
                Editar Analizador
            `;
            document.getElementById('save-analyzer-btn').innerHTML = `
                <svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                </svg>
                Actualizar Analizador
            `;

            axios.get(`/admin/analyzers/${analyzerId}`)
                .then(res => {
                    const data = res.data;
                    document.getElementById('analyzer-name').value = data.name;
                    document.getElementById('analyzer-description').value = data.description ?? '';
                    document.getElementById('analyzer-prompt').value = data.system_prompt ?? '';
                    document.getElementById('analyzer-user-prompt').value = data.user_prompt_template ?? '';
                    document.getElementById('analyzer-icon').value = data.icon ?? '';
                    document.getElementById('analyzer-type').value = data.is_system ? '1' : '0';
                    document.getElementById('analyzer-modal').classList.add('show');
                })
                .catch(err => {
                    console.error('Error fetching analyzer:', err);
                    showNotification('Error al cargar el analizador', 'error');
                });
        }

        function deleteAnalyzer(analyzerId) {
            deletingAnalyzerId = analyzerId;
            document.getElementById('delete-analyzer-modal').classList.add('show');
        }

        function closeAnalyzerModal() {
            document.getElementById('analyzer-modal').classList.remove('show');
            editingAnalyzerId = null;
        }

        function closeDeleteModal() {
            document.getElementById('delete-analyzer-modal').classList.remove('show');
            deletingAnalyzerId = null;
        }

        function saveAnalyzer() {
            const name = document.getElementById('analyzer-name').value.trim();
            const description = document.getElementById('analyzer-description').value.trim();
            const prompt = document.getElementById('analyzer-prompt').value.trim();
            const userPrompt = document.getElementById('analyzer-user-prompt').value.trim();
            const icon = document.getElementById('analyzer-icon').value.trim();
            const isSystem = document.getElementById('analyzer-type').value;

            if (!name || !description || !prompt || !userPrompt) {
                showNotification('Por favor completa todos los campos requeridos', 'error');
                return;
            }

            const btn = document.getElementById('save-analyzer-btn');
            btn.disabled = true;
            btn.innerHTML = `
                <svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                </svg>
                Guardando...
            `;

            const url = editingAnalyzerId ? `/admin/analyzers/${editingAnalyzerId}` : '/admin/analyzers';
            const method = editingAnalyzerId ? 'put' : 'post';

            const data = {
                name,
                description,
                icon,
                is_system: isSystem,
                system_prompt: prompt,
                user_prompt_template: userPrompt
            };

            axios[method](url, data)
                .then(response => {
                    const action = editingAnalyzerId ? 'actualizado' : 'creado';
                    showNotification(`Analizador ${action} exitosamente`, 'success');
                    closeAnalyzerModal();
                    loadAnalyzers();
                })
                .catch(error => {
                    console.error('Error saving analyzer:', error);
                    showNotification('Error al guardar el analizador', 'error');
                })
                .finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = `
                        <svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                        </svg>
                        ${editingAnalyzerId ? 'Actualizar' : 'Guardar'} Analizador
                    `;
                });
        }

        function confirmDeleteAnalyzer() {
            const btn = document.getElementById('confirm-delete-btn');
            btn.disabled = true;
            btn.innerHTML = `
                <svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                </svg>
                Eliminando...
            `;

            axios.delete(`/admin/analyzers/${deletingAnalyzerId}`)
                .then(response => {
                    showNotification('Analizador eliminado exitosamente', 'success');
                    closeDeleteModal();
                    loadAnalyzers();
                })
                .catch(error => {
                    console.error('Error deleting analyzer:', error);
                    showNotification('Error al eliminar el analizador', 'error');
                })
                .finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = `
                        <svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                    </svg>
                    Eliminar Analizador
                `;
                });
        }

        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;

            const icons = {
                success: '✅',
                error: '❌',
                info: 'ℹ️',
                warning: '⚠️'
            };

            notification.innerHTML = `
                <div class="notification-content">
                    <span class="notification-icon">${icons[type]}</span>
                    <span class="notification-message">${message}</span>
                </div>
            `;

            notification.style.cssText = `
                position: fixed;
                top: 2rem;
                right: 2rem;
                background: rgba(15, 23, 42, 0.95);
                backdrop-filter: blur(20px);
                border: 1px solid rgba(59, 130, 246, 0.3);
                border-radius: 12px;
                padding: 1rem 1.5rem;
                z-index: 3000;
                animation: slideIn 0.3s ease;
                color: white;
                font-weight: 500;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            `;

            document.body.appendChild(notification);

            setTimeout(() => {
                notification.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.parentNode.removeChild(notification);
                    }
                }, 300);
            }, 3000);
        }

        // Cerrar modales con ESC
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') {
                closeAnalyzerModal();
                closeDeleteModal();
            }
        });

        // Cerrar modales al hacer click fuera
        document.addEventListener('click', e => {
            if (e.target.classList.contains('modal')) {
                closeAnalyzerModal();
                closeDeleteModal();
            }
        });
    </script>
</body>
</html>
