<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Editar Perfil - Juntify</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700&display=swap" rel="stylesheet" />

    <!-- Styles -->
    @vite([
        'resources/css/app.css',
        'resources/js/app.js',
        'resources/css/index.css',
        'resources/js/index.js',
        'resources/css/profile.css',
        'resources/js/profile.js'
    ])
</head>
<body>
    <!-- Animated particles background -->
    <div class="particles" id="particles"></div>

    <!-- Navbar principal -->
    @include('partials.navbar')

    <!-- Barra de navegación móvil -->
    @include('partials.mobile-nav')

    <!-- Botón para abrir sidebar en móvil -->
    <button class="mobile-sidebar-btn mobile-menu-btn" onclick="toggleSidebar()" aria-label="Abrir menú">
        <svg class="icon-open" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 01-1.414-1.414L10.586 10 5.879 5.707a1 1 0 011.414-1.414l4.001 4a1 1 0 010 1.414l-4.001 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
        </svg>
    </button>

    <div class="app-container">
        <!-- Sidebar -->
        @include('partials.profile._sidebar')

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <div class="content-header">
                <h1 class="page-title">Editar Perfil</h1>
                <p class="page-subtitle">Actualiza tu información personal</p>
            </div>

            <!-- Edit Profile Form -->
            <div class="content-body">
                @if (session('status') === 'profile-updated')
                    <div class="alert alert-success">
                        <svg class="alert-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Perfil actualizado correctamente.
                    </div>
                @endif

                <div class="profile-card">
                    <form method="POST" action="{{ route('profile.update') }}" class="profile-form">
                        @csrf
                        @method('PATCH')

                        <!-- Nombre -->
                        <div class="form-group">
                            <label for="name" class="form-label">Nombre completo</label>
                            <input type="text" 
                                   class="form-control @error('name') error @enderror" 
                                   id="name" 
                                   name="name" 
                                   value="{{ old('name', $user->name) }}" 
                                   required>
                            @error('name')
                                <div class="error-message">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Email -->
                        <div class="form-group">
                            <label for="email" class="form-label">Correo electrónico</label>
                            <input type="email" 
                                   class="form-control @error('email') error @enderror" 
                                   id="email" 
                                   name="email" 
                                   value="{{ old('email', $user->email) }}" 
                                   required>
                            @error('email')
                                <div class="error-message">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Username -->
                        <div class="form-group">
                            <label for="username" class="form-label">Nombre de usuario</label>
                            <input type="text" 
                                   class="form-control @error('username') error @enderror" 
                                   id="username" 
                                   name="username" 
                                   value="{{ old('username', $user->username) }}" 
                                   required>
                            @error('username')
                                <div class="error-message">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                </svg>
                                Guardar cambios
                            </button>
                            <a href="{{ route('profile.show') }}" class="btn btn-secondary">
                                <svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                                Cancelar
                            </a>
                        </div>
                    </form>

                    <!-- Sección de eliminación de cuenta -->
                    <div class="danger-zone">
                        <h3 class="danger-title">Zona peligrosa</h3>
                        <p class="danger-description">Una vez que elimines tu cuenta, todos los recursos y datos se borrarán permanentemente.</p>
                        
                        <button type="button" class="btn btn-danger" onclick="showDeleteModal()">
                            <svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                            </svg>
                            Eliminar cuenta
                        </button>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal de confirmación para eliminar cuenta -->
    <div class="modal-overlay" id="deleteModal" style="display: none;" onclick="hideDeleteModal()">
        <div class="modal-content" onclick="event.stopPropagation()">
            <div class="modal-header">
                <h3 class="modal-title">Confirmar eliminación</h3>
                <button type="button" class="modal-close" onclick="hideDeleteModal()">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="modal-body">
                <p>¿Estás seguro de que quieres eliminar tu cuenta? Esta acción no se puede deshacer.</p>
                
                <form method="POST" action="{{ route('profile.destroy') }}" id="deleteAccountForm">
                    @csrf
                    @method('DELETE')
                    
                    <div class="form-group">
                        <label for="password" class="form-label">Confirma tu contraseña</label>
                        <input type="password" 
                               class="form-control @error('password', 'userDeletion') error @enderror" 
                               id="password" 
                               name="password" 
                               required>
                        @error('password', 'userDeletion')
                            <div class="error-message">{{ $message }}</div>
                        @enderror
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="hideDeleteModal()">Cancelar</button>
                <button type="submit" form="deleteAccountForm" class="btn btn-danger">Eliminar cuenta</button>
            </div>
        </div>
    </div>

    <script>
        function showDeleteModal() {
            document.getElementById('deleteModal').style.display = 'flex';
        }

        function hideDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }
    </script>

</body>
</html>