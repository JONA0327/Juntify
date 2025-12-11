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
            <h3>Agregar Integrante</h3>
            
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
                
                <div class="modal-buttons">
                    <button type="submit" class="btn-primary">Agregar Integrante</button>
                    <button type="button" class="btn-secondary" onclick="hideModal('addIntegranteModal')">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>