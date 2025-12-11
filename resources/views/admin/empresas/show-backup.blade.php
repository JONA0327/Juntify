<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $empresa->nombre_empresa }} - Detalles de Empresa</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700&display=swap" rel="stylesheet" />

    <!-- Styles -->
    @vite([
        'resources/css/app.css',
        'resources/js/app.js',
        'resources/css/index.css',
        'resources/css/profile.css',
        'resources/css/admin-empresas.css',
        'resources/js/profile.js',
        'resources/js/admin-empresas.js'
    ])
</head>
<body>
    <!-- Animated particles background -->
    <div class="particles" id="particles"></div>

    <!-- Navbar principal -->
    @include('partials.navbar')

    <!-- Contenido principal -->
    <div class="app-container">
        <main class="main-admin">
            <!-- Back button -->
            <a href="{{ route('admin.empresas.index') }}" class="back-button">
                <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16" style="margin-right: 8px;">
                    <path fill-rule="evenodd" d="M15 8a.5.5 0 0 0-.5-.5H2.707l3.147-3.146a.5.5 0 1 0-.708-.708l-4 4a.5.5 0 0 0 0 .708l4 4a.5.5 0 0 0 .708-.708L2.707 8.5H14.5A.5.5 0 0 0 15 8z"/>
                </svg>
                Volver al panel de empresas
            </a>

            <!-- Mensajes -->
            @if(session('success'))
                <div class="alert alert-success">
                    {{ session('success') }}
                </div>
            @endif

            @if($errors->any())
                <div class="alert alert-error">
                    <ul style="margin: 0; padding-left: 20px;">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- Detalles de la empresa -->
            <div class="detail-card">
                <div class="empresa-header">
                    <h1 class="empresa-title">{{ $empresa->nombre_empresa }}</h1>
                    <span class="empresa-role {{ $empresa->rol }}">{{ $empresa->rol }}</span>
                </div>

                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">ID Usuario</span>
                        <span class="info-value">{{ $empresa->iduser }}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Usuario Principal</span>
                        <span class="info-value">{{ $usuario ? $usuario->name . ' (' . $usuario->email . ')' : 'No encontrado' }}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Es Administrador</span>
                        <span class="info-value">{{ $empresa->es_administrador ? 'Sí' : 'No' }}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Fecha de Registro</span>
                        <span class="info-value">{{ $empresa->created_at->format('d/m/Y H:i') }}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Última Actualización</span>
                        <span class="info-value">{{ $empresa->updated_at->format('d/m/Y H:i') }}</span>
                    </div>
                </div>
            </div>

            <!-- Integrantes de la empresa -->
            <div class="detail-card">
                <div class="integrantes-header">
                    <h2 class="integrantes-title">Integrantes de la Empresa ({{ $empresa->integrantes->count() }})</h2>
                    <button class="btn-primary" onclick="showModal('addIntegranteModal')">
                        <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16" style="margin-right: 8px;">
                            <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/>
                        </svg>
                        Agregar Integrante
                    </button>
                </div>

                @forelse($empresa->integrantes as $integrante)
                    <div class="integrante-item">
                        <div class="integrante-info">
                            <div class="integrante-name">Usuario ID: {{ $integrante->iduser }}</div>
                            <div class="integrante-details">
                                <span>Rol: {{ $integrante->rol }}</span>
                                <span>Agregado: {{ $integrante->created_at->format('d/m/Y') }}</span>
                            </div>
                            @if($integrante->permisos && count($integrante->permisos) > 0)
                                <div class="permisos-list">
                                    @foreach($integrante->permisos as $permiso)
                                        <span class="permiso-tag">{{ $permiso }}</span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                        <div>
                            <form method="POST" action="{{ route('admin.empresas.remove-integrante', $integrante) }}" style="display: inline;" onsubmit="return confirm('¿Estás seguro de eliminar este integrante?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-secondary btn-sm" style="background: rgba(239, 68, 68, 0.2); color: #ef4444;">Eliminar</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <p style="text-align: center; color: rgba(255, 255, 255, 0.6); margin: 40px 0;">
                        Esta empresa no tiene integrantes registrados aún.
                    </p>
                @endforelse
            </div>
        </main>
    </div>

    <!-- Modal Agregar Integrante -->
    <div id="addIntegranteModal" class="form-modal">
        <div class="modal-content">
            <h3 style="color: white; margin-bottom: 24px;">Agregar Integrante</h3>

            <form method="POST" action="{{ route('admin.empresas.add-integrante', $empresa) }}">
                @csrf

                <div class="form-group">
                    <label for="iduser">ID Usuario</label>
                    <input type="number" name="iduser" id="iduser" required min="1" placeholder="ID del usuario de la BD principal">
                    <small style="color: rgba(255, 255, 255, 0.6); font-size: 12px;">Ingresa el ID del usuario que existe en la base de datos principal</small>
                </div>

                <div class="form-group">
                    <label for="rol">Rol en la Empresa</label>
                    <input type="text" name="rol" id="rol" required maxlength="100" placeholder="ej: Manager, Developer, Designer">
                </div>

                <div class="form-group">
                    <label for="permisos">Permisos (uno por línea)</label>
                    <textarea name="permisos_text" id="permisos" rows="4" placeholder="crear_proyectos&#10;editar_usuarios&#10;ver_reportes"></textarea>
                    <small style="color: rgba(255, 255, 255, 0.6); font-size: 12px;">Escribe cada permiso en una línea separada</small>
                </div>

                <div style="display: flex; gap: 12px; margin-top: 32px;">
                    <button type="submit" class="btn-primary">Agregar Integrante</button>
                    <button type="button" class="btn-secondary" onclick="hideModal('addIntegranteModal')">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
        }

        .empresa-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .empresa-title {
            font-size: 24px;
            font-weight: 700;
            color: white;
            margin: 0;
        }

        .empresa-role {
            display: inline-block;
            padding: 6px 16px;
            background: rgba(122, 24, 255, 0.2);
            color: #a855f7;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .empresa-role.founder {
            background: rgba(34, 197, 94, 0.2);
            color: #22c55e;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
        }

        .info-label {
            font-size: 14px;
            color: rgba(255, 255, 255, 0.6);
            margin-bottom: 8px;
            font-weight: 500;
        }

        .info-value {
            color: white;
            font-weight: 600;
            font-size: 16px;
        }

        .integrantes-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .integrantes-title {
            font-size: 20px;
            font-weight: 600;
            color: white;
            margin: 0;
        }

        .integrante-item {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .integrante-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .integrante-name {
            color: white;
            font-weight: 500;
            font-size: 16px;
        }

        .integrante-details {
            display: flex;
            gap: 16px;
            font-size: 14px;
            color: rgba(255, 255, 255, 0.7);
        }

        .permisos-list {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 8px;
        }

        .permiso-tag {
            background: rgba(34, 197, 94, 0.2);
            color: #22c55e;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
        }

        .form-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(5px);
            z-index: 1000;
        }

        .modal-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(30, 30, 30, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 32px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: white;
            font-weight: 500;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 16px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            color: white;
            font-size: 14px;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #7a18ff;
            box-shadow: 0 0 0 2px rgba(122, 24, 255, 0.2);
        }

        .btn-primary {
            background: linear-gradient(135deg, #7a18ff 0%, #a855f7 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-right: 12px;
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 25px rgba(122, 24, 255, 0.3);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.15);
        }

        .btn-sm {
            padding: 8px 12px;
            font-size: 12px;
            border-radius: 6px;
        }

        .alert {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid rgba(34, 197, 94, 0.3);
            color: #22c55e;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #ef4444;
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            margin-bottom: 24px;
            transition: color 0.3s ease;
        }

        .back-button:hover {
            color: white;
        }
    </style>
</head>
<body>
    <!-- Animated particles background -->
    <div class="particles" id="particles"></div>

    <!-- Navbar principal -->
    @include('partials.navbar')

    <!-- Contenido principal -->
    <div class="app-container">
        <main class="main-admin">
            <!-- Back button -->
            <a href="{{ route('admin.empresas.index') }}" class="back-button">
                <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16" style="margin-right: 8px;">
                    <path fill-rule="evenodd" d="M15 8a.5.5 0 0 0-.5-.5H2.707l3.147-3.146a.5.5 0 1 0-.708-.708l-4 4a.5.5 0 0 0 0 .708l4 4a.5.5 0 0 0 .708-.708L2.707 8.5H14.5A.5.5 0 0 0 15 8z"/>
                </svg>
                Volver al panel de empresas
            </a>

            <!-- Mensajes -->
            @if(session('success'))
                <div class="alert alert-success">
                    {{ session('success') }}
                </div>
            @endif

            @if($errors->any())
                <div class="alert alert-error">
                    <ul style="margin: 0; padding-left: 20px;">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- Detalles de la empresa -->
            <div class="detail-card">
                <div class="empresa-header">
                    <h1 class="empresa-title">{{ $empresa->nombre_empresa }}</h1>
                    <span class="empresa-role {{ $empresa->rol }}">{{ $empresa->rol }}</span>
                </div>

                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">ID Usuario</span>
                        <span class="info-value">{{ $empresa->iduser }}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Usuario Principal</span>
                        <span class="info-value">{{ $usuario ? $usuario->name . ' (' . $usuario->email . ')' : 'No encontrado' }}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Es Administrador</span>
                        <span class="info-value">{{ $empresa->es_administrador ? 'Sí' : 'No' }}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Fecha de Registro</span>
                        <span class="info-value">{{ $empresa->created_at->format('d/m/Y H:i') }}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Última Actualización</span>
                        <span class="info-value">{{ $empresa->updated_at->format('d/m/Y H:i') }}</span>
                    </div>
                </div>
            </div>

            <!-- Integrantes de la empresa -->
            <div class="detail-card">
                <div class="integrantes-header">
                    <h2 class="integrantes-title">Integrantes de la Empresa ({{ $empresa->integrantes->count() }})</h2>
                    <button class="btn-primary" onclick="showModal('addIntegranteModal')">
                        <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16" style="margin-right: 8px;">
                            <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/>
                        </svg>
                        Agregar Integrante
                    </button>
                </div>

                @forelse($empresa->integrantes as $integrante)
                    <div class="integrante-item">
                        <div class="integrante-info">
                            <div class="integrante-name">Usuario ID: {{ $integrante->iduser }}</div>
                            <div class="integrante-details">
                                <span>Rol: {{ $integrante->rol }}</span>
                                <span>Agregado: {{ $integrante->created_at->format('d/m/Y') }}</span>
                            </div>
                            @if($integrante->permisos && count($integrante->permisos) > 0)
                                <div class="permisos-list">
                                    @foreach($integrante->permisos as $permiso)
                                        <span class="permiso-tag">{{ $permiso }}</span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                        <div>
                            <form method="POST" action="{{ route('admin.empresas.remove-integrante', $integrante) }}" style="display: inline;" onsubmit="return confirm('¿Estás seguro de eliminar este integrante?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-secondary btn-sm" style="background: rgba(239, 68, 68, 0.2); color: #ef4444;">Eliminar</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <p style="text-align: center; color: rgba(255, 255, 255, 0.6); margin: 40px 0;">
                        Esta empresa no tiene integrantes registrados aún.
                    </p>
                @endforelse
            </div>
        </main>
    </div>

    <!-- Modal Agregar Integrante -->
    <div id="addIntegranteModal" class="form-modal">
        <div class="modal-content">
            <h3 style="color: white; margin-bottom: 24px;">Agregar Integrante</h3>

            <form method="POST" action="{{ route('admin.empresas.add-integrante', $empresa) }}">
                @csrf

                <div class="form-group">
                    <label for="iduser">ID Usuario</label>
                    <input type="number" name="iduser" id="iduser" required min="1" placeholder="ID del usuario de la BD principal">
                    <small style="color: rgba(255, 255, 255, 0.6); font-size: 12px;">Ingresa el ID del usuario que existe en la base de datos principal</small>
                </div>

                <div class="form-group">
                    <label for="rol">Rol en la Empresa</label>
                    <input type="text" name="rol" id="rol" required maxlength="100" placeholder="ej: Manager, Developer, Designer">
                </div>

                <div class="form-group">
                    <label for="permisos">Permisos (uno por línea)</label>
                    <textarea name="permisos_text" id="permisos" rows="4" placeholder="crear_proyectos&#10;editar_usuarios&#10;ver_reportes"></textarea>
                    <small style="color: rgba(255, 255, 255, 0.6); font-size: 12px;">Escribe cada permiso en una línea separada</small>
                </div>

                <div style="display: flex; gap: 12px; margin-top: 32px;">
                    <button type="submit" class="btn-primary">Agregar Integrante</button>
                    <button type="button" class="btn-secondary" onclick="hideModal('addIntegranteModal')">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
        }

        function hideModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Cerrar modal al hacer clic fuera
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('form-modal')) {
                e.target.style.display = 'none';
            }
        });

        // Procesar permisos como array antes de enviar
        document.querySelector('#addIntegranteModal form').addEventListener('submit', function(e) {
            const permisosTextarea = document.getElementById('permisos');
            const permisos = permisosTextarea.value
                .split('\n')
                .map(p => p.trim())
                .filter(p => p.length > 0);

            // Crear campos hidden para los permisos
            permisos.forEach((permiso, index) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = `permisos[${index}]`;
                input.value = permiso;
                this.appendChild(input);
            });
        });
    </script>
</body>
</html>
