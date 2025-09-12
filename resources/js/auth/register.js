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
  let strength = 0;
  const reqs = {
    length: password.length>=8 && password.length<=16,
    letter: /[a-zA-Z]/.test(password),
    number: /\d/.test(password),
    symbol: /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password),
  };
  Object.entries(reqs).forEach(([k,v])=>{
    document.getElementById(k+'Req').classList.toggle('met', v);
    if (v) strength++;
  });
  const fill = document.getElementById('strengthFill'),
        text = document.getElementById('strengthText'),
        pct  = (strength/4)*100;
  fill.style.width = `${pct}%`;
  if      (strength===0) { fill.className='strength-fill';             text.textContent='Ingresa una contraseña'; }
  else if (strength<=2)   { fill.className='strength-fill strength-weak';   text.textContent='Contraseña débil'; }
  else if (strength===3)  { fill.className='strength-fill strength-medium'; text.textContent='Contraseña media'; }
  else                    { fill.className='strength-fill strength-strong'; text.textContent='Contraseña fuerte'; }
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
  createParticles();

  // Variable para evitar múltiples envíos
  let formSubmitting = false;

  document.getElementById('password').addEventListener('input', e=>{
    checkPasswordStrength(e.target.value);
  });
  document.getElementById('passwordConfirmation').addEventListener('input', e=>{
    const p=document.getElementById('password').value;
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
