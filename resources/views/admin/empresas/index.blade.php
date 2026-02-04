<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Administración de Empresas - Juntify</title>

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
            <!-- Header -->
            <div class="content-header">
                <div>
                    <h1 class="page-title">Administración de Empresas</h1>
                    <p class="page-subtitle">Gestiona los usuarios con roles founder y enterprise</p>
                </div>
                <button class="btn-primary" onclick="showModal('newEmpresaModal')">
                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16" style="margin-right: 8px;">
                        <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/>
                    </svg>
                    Registrar Empresa
                </button>
            </div>

            <!-- Gestión de Roles de Usuarios -->
            <div class="admin-section">
                <div class="section-header">
                    <h2 class="section-title">🔧 Gestión de Roles de Usuarios</h2>
                    <p class="section-subtitle">Cambia el rol de cualquier usuario y asigna planes automáticamente</p>
                </div>

                <div class="user-role-manager">
                    <div class="role-form">
                        <div class="form-group">
                            <label for="search_user">Buscar Usuario</label>
                            <input type="text" id="search_user" placeholder="Busca por nombre o email..." onkeyup="searchUsers(this.value)">
                        </div>

                        <div id="user_results" class="user-results" style="display: none;"></div>

                        <div class="form-group" id="role_selector" style="display: none;">
                            <label for="new_role">Nuevo Rol</label>
                            <select id="new_role">
                                <option value="">Seleccionar rol...</option>
                                @foreach($rolesDisponibles as $rol)
                                    <option value="{{ $rol }}">
                                        @switch($rol)
                                            @case('free')
                                                🆓 Free
                                                @break
                                            @case('basic')
                                                ⭐ Basic
                                                @break
                                            @case('business')
                                                💼 Business
                                                @break
                                            @case('enterprise')
                                                🏢 Enterprise
                                                @break
                                            @case('founder')
                                                👑 Founder
                                                @break
                                            @case('bni')
                                                🤝 BNI
                                                @break
                                            @case('developer')
                                                💻 Developer
                                                @break
                                            @case('superadmin')
                                                🔐 Superadmin
                                                @break
                                            @default
                                                {{ ucfirst($rol) }}
                                        @endswitch
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group" id="role_actions" style="display: none;">
                            <button id="update_role_btn" class="btn-primary" onclick="updateUserRole()">
                                <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16" style="margin-right: 8px;">
                                    <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/>
                                    <path d="M10.97 4.97a.235.235 0 0 0-.02.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.061L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-1.071-1.05z"/>
                                </svg>
                                Actualizar Rol
                            </button>
                            <button class="btn-secondary" onclick="clearUserSelection()">Cancelar</button>
                        </div>
                    </div>

                    <div id="selected_user_info" class="selected-user-info" style="display: none;">
                        <h3>Usuario Seleccionado:</h3>
                        <div id="user_info_content"></div>
                    </div>
                </div>
            </div>

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
            @endif            <!-- Lista de empresas -->
            <div class="content-grid">
                @forelse($empresas as $empresa)
                    <div class="empresa-card">
                        <div class="empresa-header">
                            <h3 class="empresa-title">{{ $empresa->nombre_empresa }}</h3>
                            <span class="empresa-role administrador">🛡️ Administrador</span>
                        </div>

                        <div class="empresa-info">
                            <div class="info-item">
                                <span class="info-label">ID Usuario</span>
                                <span class="info-value">{{ $empresa->iduser }}</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Administrador</span>
                                <span class="info-value">{{ $empresa->es_administrador ? 'Sí' : 'No' }}</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Integrantes</span>
                                <span class="info-value">{{ $empresa->integrantes->count() }}</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Creado</span>
                                <span class="info-value">{{ $empresa->created_at->format('d/m/Y') }}</span>
                            </div>
                        </div>

                        <div class="actions">
                            <a href="{{ route('admin.empresas.show', $empresa) }}" class="btn-primary btn-sm">Ver Detalles</a>
                            <button onclick="editEmpresa({{ $empresa->id }}, '{{ $empresa->nombre_empresa }}')" class="btn-secondary btn-sm">Editar</button>
                            <form method="POST" action="{{ route('admin.empresas.destroy', $empresa) }}" style="display: inline;" onsubmit="return confirm('¿Estás seguro de eliminar esta empresa?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-secondary btn-sm" style="background: rgba(239, 68, 68, 0.2); color: #ef4444;">Eliminar</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="empresa-card">
                        <p style="text-align: center; color: rgba(255, 255, 255, 0.6); margin: 40px 0;">
                            No hay empresas registradas aún.
                        </p>
                    </div>
                @endforelse
            </div>

            <!-- Paginación -->
            <div style="margin-top: 32px;">
                {{ $empresas->links() }}
            </div>
        </main>
    </div>

    <!-- Modal Nueva Empresa -->
    <div id="newEmpresaModal" class="form-modal">
        <div class="modal-content">
            <h3>Registrar Nueva Empresa</h3>

            <form method="POST" action="{{ route('admin.empresas.store') }}">
                @csrf

                <div class="form-group">
                    <label for="iduser">Usuario (Founder/Enterprise)</label>
                    <select name="iduser" id="iduser" required>
                        <option value="">Seleccionar usuario...</option>
                        @foreach($usuariosFounderEnterprise as $user)
                            <option value="{{ $user->id }}">{{ $user->full_name }} ({{ $user->email }}) - {{ $user->roles }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label for="nombre_empresa">Nombre de la Empresa</label>
                    <input type="text" name="nombre_empresa" id="nombre_empresa" required maxlength="255">
                </div>

                <div class="form-group">
                    <label for="rol_display">Rol en la Empresa</label>
                    <div class="rol-display">
                        <span class="rol-badge administrador">Administrador</span>
                        <small style="color: rgba(255, 255, 255, 0.6); font-size: 12px; margin-top: 4px; display: block;">Se asignará automáticamente como administrador</small>
                    </div>
                    <input type="hidden" name="rol" value="administrador">
                    <input type="hidden" name="es_administrador" value="1">
                </div>

                <div class="modal-buttons">
                    <button type="submit" class="btn-primary">Registrar</button>
                    <button type="button" class="btn-secondary" onclick="hideModal('newEmpresaModal')">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Editar Empresa -->
    <div id="editEmpresaModal" class="form-modal">
        <div class="modal-content">
            <h3>Editar Empresa</h3>

            <form method="POST" id="editEmpresaForm">
                @csrf
                @method('PUT')

                <div class="form-group">
                    <label for="edit_nombre_empresa">Nombre de la Empresa</label>
                    <input type="text" name="nombre_empresa" id="edit_nombre_empresa" required maxlength="255">
                </div>

                <div class="form-group">
                    <label for="edit_rol_display">Rol en la Empresa</label>
                    <div class="rol-display">
                        <span class="rol-badge administrador">Ὦ1️ Administrador</span>
                        <small style="color: rgba(255, 255, 255, 0.6); font-size: 12px; margin-top: 4px; display: block;">Rol fijo para todas las empresas</small>
                    </div>
                    <input type="hidden" name="rol" value="administrador">
                    <input type="hidden" name="es_administrador" value="1">
                </div>

                <div class="modal-buttons">
                    <button type="submit" class="btn-primary">Actualizar</button>
                    <button type="button" class="btn-secondary" onclick="hideModal('editEmpresaModal')">Cancelar</button>
                </div>
            </form>
        </div>
    </div>


</body>
</html>
