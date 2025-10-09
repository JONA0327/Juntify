{{-- resources/views/auth/login.blade.php --}}

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>Iniciar Sesión - Juntify</title>

  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.bunny.net">
  <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700&display=swap" rel="stylesheet" />

  <!-- Styles & Scripts -->
  @vite([
    'resources/css/app.css',
    'resources/js/app.js',
    'resources/css/auth/login.css',
    'resources/js/auth/login.js'
  ])
</head>
<body>
  <div class="particles" id="particles"></div>

  <div class="auth-container">
    <div class="auth-card">
      <div class="logo">
        <h1>Juntify</h1>
        <p>Bienvenido de vuelta</p>
      </div>

      {{-- Mensaje de error genérico --}}
      @if ($errors->has('login') && $errors->first('login') !== 'password_update_required')
        <div class="error-message" style="display:block; margin-bottom:1rem;">
          {{ $errors->first('login') }}
        </div>
      @endif

      <form id="loginForm" method="POST" action="{{ route('login') }}">
        @csrf

        <div class="form-group">
          <label for="login" class="form-label">Usuario o Correo Electrónico</label>
          <input
            type="text"
            id="login"
            name="login"
            class="form-input"
            placeholder="Ingresa tu usuario o email"
            value="{{ old('login') }}"
            required
          >
          <div class="error-message" id="loginError"></div>
        </div>

        <div class="form-group">
          <label for="password" class="form-label">Contraseña</label>
          <input
            type="password"
            id="password"
            name="password"
            class="form-input"
            placeholder="Ingresa tu contraseña"
            required
          >
          <div class="error-message" id="passwordError"></div>
          <div class="forgot-password">
            <a href="{{ route('password.forgot') }}">¿Olvidaste tu contraseña?</a>
          </div>
        </div>

        <button type="submit" class="btn-primary" id="submitBtn">
          Iniciar Sesión
        </button>
      </form>

      <div class="auth-links">
        <p style="color: #cbd5e1; margin-bottom: 1rem;">¿No tienes una cuenta?</p>
        <a href="{{ route('register') }}">Regístrate en Juntify</a>
      </div>
    </div>
  </div>

  <!-- Modal para actualización de contraseña -->
  <div id="passwordUpdateModal" class="modal hidden">
    <div class="modal-content" style="max-width: 520px;">
      <div class="modal-header">
        <h3 class="modal-title">
          <svg class="modal-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 24px; height: 24px; color: #f59e0b;">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
          </svg>
          Actualización del Sistema de Contraseñas
        </h3>
        <button type="button" class="modal-close" onclick="closePasswordUpdateModal()">&times;</button>
      </div>
      <div class="modal-body">
        <div style="margin-bottom: 1.5rem;">
          <p style="color: #e2e8f0; margin-bottom: 1rem; font-size: 1.1rem; font-weight: 500;">
            🔐 <strong>Actualización de Seguridad Requerida</strong>
          </p>
          <p style="color: #cbd5e1; margin-bottom: 1rem; line-height: 1.6;">
            Hemos actualizado nuestro sistema de encriptación de contraseñas para mayor seguridad.
            Tu cuenta requiere una actualización para utilizar el nuevo método de encriptación.
          </p>
          <p style="color: #cbd5e1; margin-bottom: 1.5rem; line-height: 1.6;">
            Para continuar usando tu cuenta, necesitas restablecer tu contraseña. Este proceso es
            rápido y garantiza la máxima seguridad de tus datos.
          </p>
        </div>

        <div class="modal-actions">
          <button type="button" class="btn btn-secondary" onclick="closePasswordUpdateModal()">
            Cerrar
          </button>
          <button type="button" class="btn btn-primary" onclick="redirectToPasswordReset()">
            <svg style="width: 16px; height: 16px; margin-right: 8px;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159-.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1.035.43-1.563A6 6 0 1121.75 8.25z" />
            </svg>
            Restablecer Contraseña
          </button>
        </div>
      </div>
    </div>
  </div>

  <style>
    .modal {
      display: none;
      position: fixed;
      z-index: 1000;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0, 0, 0, 0.5);
      backdrop-filter: blur(4px);
    }

    .modal.show {
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .modal-content {
      background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
      border: 1px solid #475569;
      border-radius: 12px;
      box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3);
      max-width: 90vw;
      max-height: 90vh;
      overflow-y: auto;
    }

    .modal-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 1.5rem;
      border-bottom: 1px solid #475569;
    }

    .modal-title {
      display: flex;
      align-items: center;
      color: #f1f5f9;
      font-size: 1.25rem;
      font-weight: 600;
      margin: 0;
    }

    .modal-icon {
      margin-right: 0.75rem;
    }

    .modal-close {
      background: none;
      border: none;
      color: #94a3b8;
      font-size: 1.5rem;
      cursor: pointer;
      padding: 0;
      width: 24px;
      height: 24px;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .modal-close:hover {
      color: #f1f5f9;
    }

    .modal-body {
      padding: 1.5rem;
    }

    .modal-actions {
      display: flex;
      gap: 0.75rem;
      justify-content: flex-end;
      margin-top: 1.5rem;
    }

    .btn {
      padding: 0.75rem 1.5rem;
      border-radius: 8px;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.2s;
      border: none;
      display: inline-flex;
      align-items: center;
    }

    .btn-secondary {
      background: #475569;
      color: #f1f5f9;
    }

    .btn-secondary:hover {
      background: #64748b;
    }

    .btn-primary {
      background: #3b82f6;
      color: white;
    }

    .btn-primary:hover {
      background: #2563eb;
    }
  </style>

  <script>
    function closePasswordUpdateModal() {
      const modal = document.getElementById('passwordUpdateModal');
      modal.classList.remove('show');
      modal.classList.add('hidden');
    }

    function redirectToPasswordReset() {
      const email = document.getElementById('login').value;
      window.location.href = '{{ route("password.forgot") }}' + (email ? '?email=' + encodeURIComponent(email) : '');
    }

    // Manejar respuesta de error de actualización de contraseña
    document.addEventListener('DOMContentLoaded', function() {
      const loginForm = document.getElementById('loginForm');
      const modal = document.getElementById('passwordUpdateModal');

      if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
          // Limpiar errores previos
          document.getElementById('loginError').textContent = '';
          document.getElementById('passwordError').textContent = '';
        });
      }

      // Verificar si hay error de actualización de contraseña al cargar la página
      @if($errors->has('login') && $errors->first('login') === 'password_update_required')
        console.log('DEBUG: Password update required detected');
        setTimeout(() => {
          console.log('DEBUG: Showing modal');
          modal.classList.remove('hidden');
          modal.classList.add('show');
        }, 500);
      @endif

      // DEBUG: Mostrar todos los errores
      @if($errors->any())
        console.log('DEBUG: Errors found:', @json($errors->all()));
        console.log('DEBUG: Login error:', '@if($errors->has("login")){{ $errors->first("login") }}@endif');
      @endif
    });
  </script>
</body>
</html>
