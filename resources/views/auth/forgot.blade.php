<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>Recuperar Contraseña - Juntify</title>

  <link rel="preconnect" href="https://fonts.bunny.net">
  <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700&display=swap" rel="stylesheet" />

  @vite([
    'resources/css/app.css',
    'resources/css/auth/forgot.css',
    'resources/js/auth/forgot.js',
  ])
</head>
<body>
  <div class="particles" id="particles"></div>

  <div class="auth-container">
    <div class="auth-card">
      <div class="logo">
        <h1>Juntify</h1>
        <p>Recupera el acceso a tu cuenta</p>
      </div>

      <div id="step-email" class="step">
        <form id="forgotEmailForm">
          <div class="form-group">
            <label for="email" class="form-label">Correo Electrónico</label>
            <input type="email" id="email" name="email" class="form-input" placeholder="tu@email.com" required>
            <div class="error-message" id="emailError"></div>
          </div>
          <button type="submit" class="btn-primary" id="sendCodeBtn">Enviar código</button>
        </form>
      </div>

      <div id="step-code" class="step" style="display:none;">
        <form id="verifyCodeForm">
          <input type="hidden" id="codeEmail" name="email">
          <div class="form-group">
            <label for="code" class="form-label">Código de verificación</label>
            <input type="text" id="code" name="code" class="form-input" placeholder="Ingresa el código de 6 dígitos" maxlength="6" required>
            <div class="error-message" id="codeError"></div>
          </div>
          <button type="submit" class="btn-primary" id="verifyCodeBtn">Verificar código</button>
          <div class="auth-links" style="margin-top:1rem;">
            <a href="#" id="resendLink">Reenviar código</a>
          </div>
        </form>
      </div>

      <div id="step-reset" class="step" style="display:none;">
        <form id="resetPasswordForm">
          <input type="hidden" id="resetEmail" name="email">
          <input type="hidden" id="resetCode" name="code">

          <div class="form-group">
            <label for="password" class="form-label">Nueva Contraseña</label>
            <input type="password" id="password" name="password" class="form-input" placeholder="Nueva contraseña" required>
            <div class="error-message" id="passwordError"></div>
          </div>

          <div class="form-group">
            <label for="passwordConfirmation" class="form-label">Confirmar Contraseña</label>
            <input type="password" id="passwordConfirmation" name="password_confirmation" class="form-input" placeholder="Confirma tu contraseña" required>
            <div class="error-message" id="passwordConfirmationError"></div>
            <div class="success-message" id="passwordConfirmationSuccess"></div>
          </div>

          <button type="submit" class="btn-primary" id="resetBtn">Cambiar contraseña</button>
        </form>
      </div>

      <div class="auth-links">
        <a href="{{ route('login') }}">Volver al inicio de sesión</a>
      </div>
    </div>
  </div>

</body>
</html>
