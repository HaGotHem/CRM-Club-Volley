const CREDENTIALS = { identifiant: 'admin', mdp: 'admin' };

const form        = document.getElementById('login-form');
const inputId     = document.getElementById('identifiant');
const inputMdp    = document.getElementById('mot-de-passe');
const errId       = document.getElementById('error-identifiant');
const errMdp      = document.getElementById('error-mdp');
const errGlobal   = document.getElementById('error-global');

function showError(el, msg) {
  el.textContent = msg;
  el.classList.add('visible');
}

function clearError(el) {
  el.classList.remove('visible');
  el.addEventListener('transitionend', () => { el.textContent = ''; }, { once: true });
}

function shakeForm() {
  form.classList.remove('shake');
  void form.offsetWidth;
  form.classList.add('shake');
  form.addEventListener('animationend', () => form.classList.remove('shake'), { once: true });
}

function validateField(input, errEl) {
  if (!input.value.trim()) {
    showError(errEl, 'Ce champ est obligatoire.');
    input.classList.add('border-red-500');
    return false;
  }
  clearError(errEl);
  input.classList.remove('border-red-500');
  return true;
}

inputId.addEventListener('input', () => {
  inputId.classList.remove('input-error-flash');
  validateField(inputId, errId);
});
inputMdp.addEventListener('input', () => {
  inputMdp.classList.remove('input-error-flash');
  validateField(inputMdp, errMdp);
});

form.addEventListener('submit', (e) => {
  e.preventDefault();
  clearError(errGlobal);

  const validId  = validateField(inputId,  errId);
  const validMdp = validateField(inputMdp, errMdp);

  if (!validId || !validMdp) return;

  const id  = inputId.value.trim();
  const mdp = inputMdp.value.trim();

  if (id !== CREDENTIALS.identifiant || mdp !== CREDENTIALS.mdp) {
    showError(errGlobal, '⚠ Identifiant ou mot de passe incorrect.');
    shakeForm();
    [inputId, inputMdp].forEach(el => el.classList.add('border-red-500', 'input-error-flash'));
    inputMdp.value = '';
    inputMdp.focus();
    return;
  }

  console.log('Connexion réussie');
  window.location.href = './src/content/Dashboard.html';
});
