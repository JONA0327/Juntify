<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>Registro - Juntify</title>

  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.bunny.net">
  <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700&display=swap" rel="stylesheet"/>

  <!-- Styles -->
  @vite([
    'resources/css/app.css',
    'resources/css/auth/register.css',
  ])
</head>
<body>
  <div class="particles" id="particles"></div>

  <div class="auth-container">
    <div class="auth-card">
      <div class="logo">
        <h1>Juntify</h1>
        <p>Únete a la revolución de las reuniones</p>
      </div>

      <form id="registerForm" method="POST" action="{{ route('register') }}">
        @csrf

        <div class="form-group">
          <label for="username" class="form-label">Usuario</label>
          <input type="text" id="username" name="username"
                 class="form-input" placeholder="Elige tu nombre de usuario"
                 value="{{ old('username') }}" required>
          <div class="error-message" id="usernameError"></div>
        </div>

        <div class="form-group">
          <label for="fullName" class="form-label">Nombre Completo</label>
          <input type="text" id="fullName" name="full_name"
                 class="form-input" placeholder="Tu nombre completo"
                 value="{{ old('full_name') }}" required>
          <div class="error-message" id="fullNameError"></div>
        </div>

        <div class="form-group">
          <label for="email" class="form-label">Correo Electrónico</label>
          <input type="email" id="email" name="email"
                 class="form-input" placeholder="tu@email.com"
                 value="{{ old('email') }}" required>
          <div class="error-message" id="emailError"></div>
        </div>

        <div class="form-group">
          <label for="password" class="form-label">Contraseña</label>
          <input type="password" id="password" name="password"
                 class="form-input" placeholder="Crea una contraseña segura"
                 required>
          <div class="password-strength">
            <div class="strength-bar">
              <div class="strength-fill" id="strengthFill"></div>
            </div>
            <div class="strength-text" id="strengthText">Ingresa una contraseña</div>
          </div>
          <div class="password-requirements">
            <div class="requirement" id="lengthReq">8-16 caracteres</div>
            <div class="requirement" id="letterReq">Al menos una letra</div>
            <div class="requirement" id="numberReq">Al menos un número</div>
            <div class="requirement" id="symbolReq">Al menos un símbolo especial</div>
          </div>
          <div class="error-message" id="passwordError"></div>
        </div>

        <div class="form-group">
          <label for="passwordConfirmation" class="form-label">Confirmar Contraseña</label>
          <input type="password" id="passwordConfirmation" name="password_confirmation"
                 class="form-input" placeholder="Confirma tu contraseña"
                 required>
          <div class="error-message" id="passwordConfirmationError"></div>
          <div class="success-message" id="passwordConfirmationSuccess"></div>
        </div>

        {{-- Rol por defecto como string --}}
        <input type="hidden" name="role" value="free">

        <button type="submit" class="btn-primary" id="submitBtn">
          Crear Cuenta
        </button>
      </form>

      <div class="auth-links">
        <p style="color: #cbd5e1; margin-bottom: 1rem;">¿Ya tienes una cuenta?</p>
        <a href="{{ route('login') }}">Inicia sesión en Juntify</a>
      </div>
    </div>
  </div>

  <!-- Success Modal -->
  <div class="modal" id="successModal">
    <div class="modal-content">
      <div class="modal-icon">🎉</div>
      <h2 class="modal-title">¡Enhorabuena!</h2>
      <p class="modal-message">
        Te has registrado exitosamente en Juntify.<br>
        Bienvenido a la revolución de las reuniones.
      </p>
      <button class="modal-btn" onclick="redirectToProfile()">
        Continuar
      </button>
    </div>
  </div>

  @vite([
    'resources/js/app.js',
    'resources/js/auth/register.js',
  ])

  @if(session('registered'))
  <script>
    document.addEventListener('DOMContentLoaded', function(){
      showSuccessModal();
    });
  </script>
  @endif
</body>
</html>
