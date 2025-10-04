import '../bootstrap';
import bcrypt from 'bcryptjs';
import { debugLog, debugWarn, debugError } from '../utils/logger';

// ———————————————————
//  Animación de partículas
// ———————————————————
function createParticles() {
  const container = document.getElementById('particles');
  for (let i = 0; i < 80; i++) {
    const p = document.createElement('div');
    p.className = 'particle';
    p.style.left = `${Math.random()*100}%`;
    p.style.top  = `${Math.random()*100}%`;
    p.style.animationDelay    = `${Math.random()*8}s`;
    p.style.animationDuration = `${Math.random()*4+4}s`;
    container.appendChild(p);
  }
}

// ———————————————————
//  Comprobación de fuerza
// ———————————————————
function checkPasswordStrength(password) {
  debugLog('Checking password strength for:', password);
  let strength = 0;

  // Test simple primero
  const hasLength = password.length >= 8 && password.length <= 16;
  const hasLetter = /[a-zA-Z]/.test(password);
  const hasNumber = /\d/.test(password);
  const hasSymbol = /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password);

  debugLog('Tests:', {
    hasLength,
    hasLetter,
    hasNumber,
    hasSymbol
  });

  const reqs = {
    length: hasLength,
    letter: hasLetter,
    number: hasNumber,
    symbol: hasSymbol,
  };

  debugLog('Requirements:', reqs);

  Object.entries(reqs).forEach(([k,v])=>{
    const element = document.getElementById(k+'Req');
    debugLog(`Looking for element: ${k}Req`);
    if (element) {
      debugLog(`Found element ${k}Req, setting class:`, v ? 'met' : 'not met');
      element.classList.toggle('met', v);
      if (v) strength++;
    } else {
      debugWarn(`Element ${k}Req not found`);
    }
  });

  const fill = document.getElementById('strengthFill'),
        text = document.getElementById('strengthText'),
        pct  = (strength/4)*100;

  debugLog('Strength:', strength, 'Percentage:', pct);

  if (fill) {
    fill.style.width = `${pct}%`;
    fill.className = 'strength-fill';
    if (strength <= 2) fill.classList.add('strength-weak');
    else if (strength === 3) fill.classList.add('strength-medium');
    else if (strength === 4) fill.classList.add('strength-strong');
    debugLog('Updated fill element');
  } else {
    debugWarn('strengthFill element not found');
  }

  if (text) {
    if      (strength===0) { text.textContent='Ingresa una contraseña'; }
    else if (strength<=2)   { text.textContent='Contraseña débil'; }
    else if (strength===3)  { text.textContent='Contraseña media'; }
    else                    { text.textContent='Contraseña fuerte'; }
    debugLog('Updated text element to:', text.textContent);
  } else {
    debugWarn('strengthText element not found');
  }

  debugLog('Password strength:', strength);
  return strength===4;
}

// ———————————————————
//  Mensajes de error/éxito
// ———————————————————
function showError(id, msg) {
  const f = document.getElementById(id),
        e = document.getElementById(id+'Error');
  f.classList.add('error'); f.classList.remove('success');
  e.textContent=msg; e.style.display='block';
}
function showSuccess(id,msg) {
  const f = document.getElementById(id),
        s = document.getElementById(id+'Success'),
        e = document.getElementById(id+'Error');
  f.classList.add('success'); f.classList.remove('error');
  if(e){ e.style.display='none'; e.textContent=''; }
  if(s){ s.textContent=msg; s.style.display='block'; }
}
function clearErrors() {
  document.querySelectorAll('.form-input').forEach(i=> i.classList.remove('error','success'));
  document.querySelectorAll('.error-message').forEach(e=>{ e.style.display='none'; e.textContent=''; });
  document.querySelectorAll('.success-message').forEach(s=>{ s.style.display='none'; s.textContent=''; });
}

// ———————————————————
//  Validación completa
// ———————————————————
function validateForm() {
  clearErrors();
  let ok = true;
  const u  = document.getElementById('username'),
        fn = document.getElementById('fullName'),
        m  = document.getElementById('email'),
        p  = document.getElementById('password'),
        pc = document.getElementById('passwordConfirmation');
  if(!u.value.trim()){ showError('username','Usuario requerido'); ok=false; }
  else if(u.value.length<3){ showError('username','Min 3 caracteres'); ok=false; }
  if(!fn.value.trim()){ showError('fullName','Nombre completo requerido'); ok=false; }
  const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  if(!m.value.trim()){ showError('email','Correo requerido'); ok=false; }
  else if(!re.test(m.value)){ showError('email','Correo inválido'); ok=false; }
  if(!p.value){ showError('password','Contraseña requerida'); ok=false; }
  else if(!checkPasswordStrength(p.value)){ showError('password','No cumple requisitos'); ok=false; }
  if(!pc.value){ showError('passwordConfirmation','Confirma contraseña'); ok=false; }
  else if(p.value!==pc.value){ showError('passwordConfirmation','No coinciden'); ok=false; }
  else showSuccess('passwordConfirmation','¡Coinciden!');
  return ok;
}

// ———————————————————
//  Mostrar modal y redirección
// ———————————————————
//  Inicialización
// ———————————————————
document.addEventListener('DOMContentLoaded', ()=>{
  debugLog('DOM Content Loaded, initializing registration form...');

  createParticles();

  // Variable para evitar múltiples envíos
  let formSubmitting = false;

  // Verificar que los elementos necesarios existan
  const passwordField = document.getElementById('password');
  const passwordConfirmationField = document.getElementById('passwordConfirmation');
  const registerForm = document.getElementById('registerForm');

  if (!passwordField) {
    debugError('Password field not found');
    return;
  }

  if (!passwordConfirmationField) {
    debugError('Password confirmation field not found');
    return;
  }

  if (!registerForm) {
    debugError('Register form not found');
    return;
  }

  debugLog('All required elements found, setting up event listeners...');

  // Test inicial de la función de strength
  setTimeout(() => {
    debugLog('Testing password strength function...');

    // Test básico: cambiar el color de los requirements manualmente
    const lengthReq = document.getElementById('lengthReq');
    const letterReq = document.getElementById('letterReq');
    const numberReq = document.getElementById('numberReq');
    const symbolReq = document.getElementById('symbolReq');

    debugLog('Elements found:', {
      lengthReq: !!lengthReq,
      letterReq: !!letterReq,
      numberReq: !!numberReq,
      symbolReq: !!symbolReq
    });

    // Test visual directo
    if (lengthReq) {
      lengthReq.classList.add('met');
      debugLog('Added met class to lengthReq');
    }

    checkPasswordStrength('');
  }, 500);

  passwordField.addEventListener('input', e=>{
    debugLog('Password input event triggered with value:', e.target.value);

    // Test simple directo
    const password = e.target.value;
    const lengthReq = document.getElementById('lengthReq');
    const letterReq = document.getElementById('letterReq');
    const numberReq = document.getElementById('numberReq');
    const symbolReq = document.getElementById('symbolReq');

    // Test de longitud
    if (lengthReq) {
      if (password.length >= 8 && password.length <= 16) {
        lengthReq.classList.add('met');
      } else {
        lengthReq.classList.remove('met');
      }
    }

    // Test de letra
    if (letterReq) {
      if (/[a-zA-Z]/.test(password)) {
        letterReq.classList.add('met');
      } else {
        letterReq.classList.remove('met');
      }
    }

    // Test de número
    if (numberReq) {
      if (/\d/.test(password)) {
        numberReq.classList.add('met');
      } else {
        numberReq.classList.remove('met');
      }
    }

    // Test de símbolo
    if (symbolReq) {
      if (/[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password)) {
        symbolReq.classList.add('met');
      } else {
        symbolReq.classList.remove('met');
      }
    }

    // También ejecutar la función original
    checkPasswordStrength(e.target.value);
  });

  passwordConfirmationField.addEventListener('input', e=>{
    debugLog('Password confirmation input event triggered');
    const p = passwordField.value;
    if(e.target.value && p) {
      p===e.target.value
        ? showSuccess('passwordConfirmation','¡Coinciden!')
        : showError('passwordConfirmation','No coinciden');
    }
  });

  document.getElementById('registerForm').addEventListener('submit', async function(e){
    e.preventDefault();
    e.stopPropagation();

    // Evitar múltiples envíos
    if (formSubmitting) return false;
    if(!validateForm()) return false;
    formSubmitting = true;
    const btn = document.getElementById('submitBtn');
    btn.classList.add('loading');
    btn.textContent = 'Creando cuenta...';
    btn.disabled = true;

    // hash con bcryptjs (cost 10)
    const pwdEl  = document.getElementById('password'),
          pcEl   = document.getElementById('passwordConfirmation'),
          originalPwd = pwdEl.value,
          salt   = bcrypt.genSaltSync(10),
          hash   = bcrypt.hashSync(originalPwd, salt);

    // Crear inputs hidden para enviar el hash
    const hiddenPwd = document.createElement('input');
    hiddenPwd.type = 'hidden';
    hiddenPwd.name = 'password_hash';
    hiddenPwd.value = hash;
    this.appendChild(hiddenPwd);

    const hiddenPc = document.createElement('input');
    hiddenPc.type = 'hidden';
    hiddenPc.name = 'password_confirmation_hash';
    hiddenPc.value = hash;
    this.appendChild(hiddenPc);

    // Limpiar los campos visibles antes del envío
    pwdEl.value = '';
    pcEl.value = '';
    pwdEl.placeholder = 'Contraseña enviada...';
    pcEl.placeholder = 'Confirmación enviada...';
    pwdEl.disabled = true;
    pcEl.disabled = true;

    // Enviar con fetch para capturar errores de usuario/correo
    const formData = new FormData(this);
    try {
      const resp = await fetch(this.action, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
        body: formData
      });
      if (resp.status === 422) {
        const data = await resp.json();
        let msg = '';
        if (data.errors) {
          if (data.errors.username) msg += data.errors.username + '\n';
          if (data.errors.email) msg += data.errors.email + '\n';
        }
        showRegisterModal(msg.trim() || 'El usuario o correo ya existen.');
        // Limpiar campos del formulario
        document.getElementById('username').value = '';
        document.getElementById('fullName').value = '';
        document.getElementById('email').value = '';
        document.getElementById('password').value = '';
        document.getElementById('passwordConfirmation').value = '';
        btn.classList.remove('loading');
        btn.textContent = 'Crear Cuenta';
        btn.disabled = false;
        formSubmitting = false;
        pwdEl.disabled = false;
        pcEl.disabled = false;
        return;
      }
      // Si todo ok, mostrar modal de éxito y redirigir al perfil
      const data = await resp.json();
      if (data.success) {
        showRegisterSuccessModal('¡Enhorabuena! Te has registrado a Juntify', data.redirect);
        return;
      }
      // fallback: submit tradicional
      this.submit();
  // Modal de éxito tras registro
  function showRegisterSuccessModal(msg, redirectUrl) {
    let modal = document.getElementById('registerSuccessModal');
    if (!modal) {
      modal = document.createElement('div');
      modal.id = 'registerSuccessModal';
      modal.style.position = 'fixed';
      modal.style.top = '0';
      modal.style.left = '0';
      modal.style.width = '100vw';
      modal.style.height = '100vh';
      modal.style.background = 'rgba(0,0,0,0.5)';
      modal.style.display = 'flex';
      modal.style.alignItems = 'center';
      modal.style.justifyContent = 'center';
      modal.style.zIndex = '9999';
      modal.innerHTML = `<div style="background:#1e293b;padding:2rem 2.5rem;border-radius:1rem;max-width:90vw;box-shadow:0 8px 32px #000a;min-width:300px;text-align:center;">
        <h2 style='color:#4ade80;font-size:1.3rem;margin-bottom:1rem;'>¡Enhorabuena!</h2>
        <div style='color:#fff;margin-bottom:1.5rem;white-space:pre-line;'>${msg}</div>
        <button id='closeRegisterSuccessBtn' style='background:#4ade80;color:#222;padding:0.5rem 1.5rem;border:none;border-radius:0.5rem;font-size:1rem;cursor:pointer;'>Aceptar</button>
      </div>`;
      document.body.appendChild(modal);
    } else {
      modal.querySelector('div > div').innerHTML = msg;
      modal.style.display = 'flex';
    }
    document.getElementById('closeRegisterSuccessBtn').onclick = ()=>{
      modal.style.display = 'none';
      if (redirectUrl) window.location.href = redirectUrl;
    };
  }
    } catch (err) {
      showRegisterModal('Error de red o servidor. Intenta de nuevo.');
      btn.classList.remove('loading');
      btn.textContent = 'Crear Cuenta';
      btn.disabled = false;
      formSubmitting = false;
      pwdEl.disabled = false;
      pcEl.disabled = false;
    }
  });

  // Modal simple para errores de registro
  function showRegisterModal(msg) {
    let modal = document.getElementById('registerErrorModal');
    if (!modal) {
      modal = document.createElement('div');
      modal.id = 'registerErrorModal';
      modal.style.position = 'fixed';
      modal.style.top = '0';
      modal.style.left = '0';
      modal.style.width = '100vw';
      modal.style.height = '100vh';
      modal.style.background = 'rgba(0,0,0,0.5)';
      modal.style.display = 'flex';
      modal.style.alignItems = 'center';
      modal.style.justifyContent = 'center';
      modal.style.zIndex = '9999';
      modal.innerHTML = `<div style="background:#1e293b;padding:2rem 2.5rem;border-radius:1rem;max-width:90vw;box-shadow:0 8px 32px #000a;min-width:300px;text-align:center;">
        <h2 style='color:#f87171;font-size:1.3rem;margin-bottom:1rem;'>No se pudo registrar</h2>
        <div style='color:#fff;margin-bottom:1.5rem;white-space:pre-line;'>${msg}</div>
        <button id='closeRegisterModalBtn' style='background:#f87171;color:#fff;padding:0.5rem 1.5rem;border:none;border-radius:0.5rem;font-size:1rem;cursor:pointer;'>Cerrar</button>
      </div>`;
      document.body.appendChild(modal);
    } else {
      modal.querySelector('div > div').innerHTML = msg;
      modal.style.display = 'flex';
    }
    document.getElementById('closeRegisterModalBtn').onclick = ()=>{
      modal.style.display = 'none';
    };
  }
});
