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
      @if ($errors->has('login'))
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
</body>
</html>
