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

    console.log('Formulario enviado, validando...');
    if(!validateForm()) {
      console.log('Validación fallida');
      return;
    }

    console.log('Validación exitosa, procesando...');

    // hash con bcryptjs (cost 10)
    const pwdEl  = document.getElementById('password'),
          pcEl   = document.getElementById('passwordConfirmation'),
          salt   = bcrypt.genSaltSync(10),
          hash   = bcrypt.hashSync(pwdEl.value, salt);

    pwdEl.value  = hash;
    pcEl.value   = hash;

    const btn = document.getElementById('submitBtn');
    btn.classList.add('loading');
    btn.textContent = 'Creando cuenta...';
    btn.disabled = true;

    console.log('Enviando formulario...');

    // Enviar formulario usando fetch para tener más control
    try {
      const formData = new FormData(this);
      const response = await fetch(this.action, {
        method: 'POST',
        body: formData,
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin'
      });

      if (response.ok) {
        const data = await response.json();
        console.log('Registro exitoso, redirigiendo...', data);
        if (data.redirect_url) {
          window.location.href = data.redirect_url;
        } else {
          window.location.href = '/profile';
        }
      } else {
        console.error('Error en el registro:', response.status);
        btn.classList.remove('loading');
        btn.textContent = 'Crear Cuenta';
        btn.disabled = false;

        // Mostrar errores del servidor
        const errorData = await response.json().catch(() => null);
        if (errorData && errorData.errors) {
          Object.keys(errorData.errors).forEach(field => {
            showError(field, errorData.errors[field][0]);
          });
        } else {
          alert('Error en el registro. Por favor intenta de nuevo.');
        }
      }
    } catch (error) {
      console.error('Error de red:', error);
      btn.classList.remove('loading');
      btn.textContent = 'Crear Cuenta';
      btn.disabled = false;
      alert('Error de conexión. Por favor intenta de nuevo.');
    }
  });
});
