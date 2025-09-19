import '../bootstrap';
import bcrypt from 'bcryptjs';

function createParticles(){
  const c=document.getElementById('particles');
  if(!c) return;
  for(let i=0;i<80;i++){
    const p=document.createElement('div');
    p.className='particle';
    p.style.left=Math.random()*100+'%';
    p.style.top=Math.random()*100+'%';
    p.style.animationDelay=Math.random()*8+'s';
    p.style.animationDuration=(Math.random()*4+4)+'s';
    c.appendChild(p);
  }
}

function showError(id,msg){ const e=document.getElementById(id+'Error'); const f=document.getElementById(id); if(f) f.classList.add('error'); if(e){ e.textContent=msg; e.style.display='block'; } }
function clearErrors(){ document.querySelectorAll('.form-input').forEach(i=>i.classList.remove('error','success')); document.querySelectorAll('.error-message').forEach(e=>{e.style.display='none'; e.textContent='';}); document.querySelectorAll('.success-message').forEach(s=>{s.style.display='none'; s.textContent='';}); }
function switchStep(fromId,toId){ const a=document.getElementById(fromId), b=document.getElementById(toId); if(a) a.style.display='none'; if(b) b.style.display='block'; }

async function postJSON(url, data){
  const token=document.querySelector('meta[name="csrf-token"]').content;
  const r=await fetch(url,{method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':token,'Accept':'application/json'}, body: JSON.stringify(data)});
  const ct=r.headers.get('content-type')||''; let j=null; if(ct.includes('application/json')){ j=await r.json(); }
  if(!r.ok){ throw new Error((j&&j.message)||'Error'); }
  return j;
}

document.addEventListener('DOMContentLoaded', ()=>{
  createParticles();

  const emailForm = document.getElementById('forgotEmailForm');
  const codeForm  = document.getElementById('verifyCodeForm');
  const resetForm = document.getElementById('resetPasswordForm');

  if(emailForm){
    emailForm.addEventListener('submit', async (e)=>{
      e.preventDefault(); clearErrors();
      const email = document.getElementById('email').value.trim();
      const btn = document.getElementById('sendCodeBtn');
      if(!email){ showError('email','Correo requerido'); return; }
      btn.classList.add('loading'); btn.textContent='Enviando...';
      try{
        await postJSON('/forgot-password/send-code',{email});
        // pasar a step code
        document.getElementById('codeEmail').value=email;
        switchStep('step-email','step-code');
      }catch(err){ showError('email', err.message); }
      finally{ btn.classList.remove('loading'); btn.textContent='Enviar código'; }
    });
  }

  const resendLink = document.getElementById('resendLink');
  if(resendLink){
    resendLink.addEventListener('click', async (e)=>{
      e.preventDefault();
      const email = document.getElementById('codeEmail').value;
      try{ await postJSON('/forgot-password/send-code',{email}); alert('Código reenviado'); }catch(_e){ alert('No se pudo reenviar el código'); }
    });
  }

  if(codeForm){
    codeForm.addEventListener('submit', async (e)=>{
      e.preventDefault(); clearErrors();
      const email = document.getElementById('codeEmail').value;
      const code  = document.getElementById('code').value.trim();
      const btn   = document.getElementById('verifyCodeBtn');
      if(!code || code.length!==6){ showError('code','Ingresa el código de 6 dígitos'); return; }
      btn.classList.add('loading'); btn.textContent='Verificando...';
      try{
        await postJSON('/forgot-password/verify-code',{email, code});
        // avanzar a reset
        document.getElementById('resetEmail').value=email;
        document.getElementById('resetCode').value=code;
        switchStep('step-code','step-reset');
      }catch(err){
        const m = err.message || '';
        if(m.toLowerCase().includes('expir')) showError('code','El código ha expirado.');
        else showError('code','Código incorrecto.');
      } finally { btn.classList.remove('loading'); btn.textContent='Verificar código'; }
    });
  }

  if(resetForm){
    resetForm.addEventListener('submit', async (e)=>{
      e.preventDefault(); clearErrors();
      const email = document.getElementById('resetEmail').value;
      const code  = document.getElementById('resetCode').value;
      const p  = document.getElementById('password');
      const pc = document.getElementById('passwordConfirmation');
      const btn= document.getElementById('resetBtn');
      if(!p.value){ showError('password','Contraseña requerida'); return; }
      if(p.value!==pc.value){ showError('passwordConfirmation','No coinciden'); return; }
      btn.classList.add('loading'); btn.textContent='Actualizando...';
      try{
        const salt=bcrypt.genSaltSync(10);
        const hash=bcrypt.hashSync(p.value, salt);
        await postJSON('/forgot-password/reset',{ email, code, password_hash: hash, password_confirmation_hash: hash });
        alert('Contraseña actualizada con éxito');
        window.location.href = '/login';
      }catch(err){
        const m = err.message || '';
        if(m.toLowerCase().includes('expir')) alert('El código ha expirado.');
        else alert('No se pudo actualizar la contraseña');
      } finally { btn.classList.remove('loading'); btn.textContent='Cambiar contraseña'; }
    });
  }
});
