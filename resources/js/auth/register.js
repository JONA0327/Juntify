import '../bootstrap';
import bcrypt from 'bcryptjs';

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
  console.log('Checking password strength for:', password);
  let strength = 0;

  // Test simple primero
  const hasLength = password.length >= 8 && password.length <= 16;
  const hasLetter = /[a-zA-Z]/.test(password);
  const hasNumber = /\d/.test(password);
  const hasSymbol = /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password);

  console.log('Tests:', {
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

  console.log('Requirements:', reqs);

  Object.entries(reqs).forEach(([k,v])=>{
    const element = document.getElementById(k+'Req');
    console.log(`Looking for element: ${k}Req`);
    if (element) {
      console.log(`Found element ${k}Req, setting class:`, v ? 'met' : 'not met');
      element.classList.toggle('met', v);
      if (v) strength++;
    } else {
      console.warn(`Element ${k}Req not found`);
    }
  });

  const fill = document.getElementById('strengthFill'),
        text = document.getElementById('strengthText'),
        pct  = (strength/4)*100;

  console.log('Strength:', strength, 'Percentage:', pct);

  if (fill) {
    fill.style.width = `${pct}%`;
    fill.className = 'strength-fill';
    if (strength <= 2) fill.classList.add('strength-weak');
    else if (strength === 3) fill.classList.add('strength-medium');
    else if (strength === 4) fill.classList.add('strength-strong');
    console.log('Updated fill element');
  } else {
    console.warn('strengthFill element not found');
  }

  if (text) {
    if      (strength===0) { text.textContent='Ingresa una contraseña'; }
    else if (strength<=2)   { text.textContent='Contraseña débil'; }
    else if (strength===3)  { text.textContent='Contraseña media'; }
    else                    { text.textContent='Contraseña fuerte'; }
    console.log('Updated text element to:', text.textContent);
  } else {
    console.warn('strengthText element not found');
  }

  console.log('Password strength:', strength);
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
  console.log('DOM Content Loaded, initializing registration form...');

  createParticles();

  // Variable para evitar múltiples envíos
  let formSubmitting = false;

  // Verificar que los elementos necesarios existan
  const passwordField = document.getElementById('password');
  const passwordConfirmationField = document.getElementById('passwordConfirmation');
  const registerForm = document.getElementById('registerForm');

  if (!passwordField) {
    console.error('Password field not found');
    return;
  }

  if (!passwordConfirmationField) {
    console.error('Password confirmation field not found');
    return;
  }

  if (!registerForm) {
    console.error('Register form not found');
    return;
  }

  console.log('All required elements found, setting up event listeners...');

  // Test inicial de la función de strength
  setTimeout(() => {
    console.log('Testing password strength function...');

    // Test básico: cambiar el color de los requirements manualmente
    const lengthReq = document.getElementById('lengthReq');
    const letterReq = document.getElementById('letterReq');
    const numberReq = document.getElementById('numberReq');
    const symbolReq = document.getElementById('symbolReq');

    console.log('Elements found:', {
      lengthReq: !!lengthReq,
      letterReq: !!letterReq,
      numberReq: !!numberReq,
      symbolReq: !!symbolReq
    });

    // Test visual directo
    if (lengthReq) {
      lengthReq.classList.add('met');
      console.log('Added met class to lengthReq');
    }

    checkPasswordStrength('');
  }, 500);

  passwordField.addEventListener('input', e=>{
    console.log('Password input event triggered with value:', e.target.value);

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
    console.log('Password confirmation input event triggered');
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

    console.log('Formulario enviado, validando...');

    // Evitar múltiples envíos
    if (formSubmitting) {
      console.log('Formulario ya se está enviando, ignorando...');
      return false;
    }

    if(!validateForm()) {
      console.log('Validación fallida');
      return false;
    }

    console.log('Validación exitosa, procesando...');
    formSubmitting = true;

    const btn = document.getElementById('submitBtn');

    btn.classList.add('loading');
    btn.textContent = 'Creando cuenta...';
    btn.disabled = true;

    // hash con bcryptjs (cost 10)
    const pwdEl  = document.getElementById('password'),
          pcEl   = document.getElementById('passwordConfirmation'),
          originalPwd = pwdEl.value,  // Guardar valor original
          originalPc = pcEl.value,    // Guardar valor original
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

    // Deshabilitar los campos originales para que no se envíen
    pwdEl.disabled = true;
    pcEl.disabled = true;

    console.log('Hash generado, enviando formulario tradicional...');

    // Usar setTimeout para permitir que el DOM se actualice antes del envío
    setTimeout(() => {
      this.submit();
    }, 100);
  });
});
