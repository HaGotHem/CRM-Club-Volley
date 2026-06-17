// Script de test de l'API Brevo (https://developers.brevo.com/)
// Permet de vérifier que la clé API fonctionne (GET /account)
// et de lister les contacts (GET /contacts).

const BREVO_BASE_URL = 'https://api.brevo.com/v3';

const inputApiKey   = document.getElementById('brevo-api-key');
const btnTestAccount = document.getElementById('btn-test-account');
const btnTestContacts = document.getElementById('btn-test-contacts');
const statusEl       = document.getElementById('api-status');
const resultEl        = document.getElementById('api-result');

function setStatus(message, isError = false) {
  statusEl.textContent = message;
  statusEl.classList.toggle('text-error', isError);
  statusEl.classList.toggle('text-success', !isError);
}

function clearResult() {
  resultEl.innerHTML = '';
}

function escapeHtml(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');
}

function createCard(title, rows) {
  const card = document.createElement('div');
  card.className = 'card bg-[var(--color-cards)] card-xl shadow-[0_10px_30px_rgba(0,0,0,0.5)]';

  const rowsHtml = rows
    .filter(([, value]) => value !== undefined && value !== null && value !== '')
    .map(([label, value]) => `
      <div class="flex justify-between gap-2 text-sm">
        <span class="opacity-70">${escapeHtml(label)}</span>
        <span class="font-medium text-right">${escapeHtml(value)}</span>
      </div>
    `)
    .join('');

  card.innerHTML = `
    <div class="card-body gap-2">
      <h2 class="card-title">${escapeHtml(title)}</h2>
      ${rowsHtml}
    </div>
  `;

  return card;
}

function renderAccountCard(account) {
  clearResult();
  const plan = Array.isArray(account.plan) ? account.plan[0] : account.plan;

  const card = createCard(account.companyName || account.email, [
    ['Email', account.email],
    ['Prénom', account.firstName],
    ['Nom', account.lastName],
    ['Type de plan', plan?.type],
    ['Crédits', plan?.credits],
    ['Adresse', [account.address?.city, account.address?.country].filter(Boolean).join(', ')],
  ]);

  resultEl.appendChild(card);
}

function renderContactsCards(data) {
  clearResult();
  const contacts = data.contacts || [];

  if (contacts.length === 0) {
    const empty = document.createElement('p');
    empty.className = 'opacity-70 text-sm';
    empty.textContent = 'Aucun contact trouvé.';
    resultEl.appendChild(empty);
    return;
  }

  contacts.forEach((contact) => {
    const attrs = contact.attributes || {};
    const card = createCard(
      [attrs.FIRSTNAME, attrs.LASTNAME].filter(Boolean).join(' ') || contact.email,
      [
        ['Email', contact.email],
        ['Téléphone', attrs.SMS || attrs.PHONE],
        ['Listes', contact.listIds?.join(', ')],
        ['Créé le', contact.createdAt && new Date(contact.createdAt).toLocaleDateString('fr-FR')],
      ]
    );
    resultEl.appendChild(card);
  });
}

function renderError(message) {
  clearResult();
  const card = document.createElement('div');
  card.className = 'card bg-[var(--color-cards)] card-xl shadow-[0_10px_30px_rgba(0,0,0,0.5)] border border-error';
  card.innerHTML = `
    <div class="card-body gap-2">
      <h2 class="card-title text-error">Erreur</h2>
      <p class="text-sm">${escapeHtml(message)}</p>
    </div>
  `;
  resultEl.appendChild(card);
}

function getApiKey() {
  const key = inputApiKey.value.trim();
  if (!key) {
    setStatus('Merci de saisir une clé API Brevo.', true);
    return null;
  }
  localStorage.setItem('brevoApiKey', key);
  return key;
}

async function callBrevo(path, apiKey) {
  const url = `${BREVO_BASE_URL}${path}`;
  console.groupCollapsed(`[Brevo] GET ${path}`);
  console.log('URL complète :', url);
  console.log('Clé API utilisée :', apiKey ? `${apiKey.slice(0, 6)}...${apiKey.slice(-4)}` : '(vide)');

  const response = await fetch(url, {
    method: 'GET',
    headers: {
      'accept': 'application/json',
      'api-key': apiKey,
    },
  });

  console.log('Statut HTTP :', response.status, response.statusText);

  const data = await response.json().catch((err) => {
    console.error('Impossible de parser la réponse JSON :', err);
    return null;
  });

  console.log('Corps de la réponse :', data);

  if (!response.ok) {
    const message = data?.message || `Erreur HTTP ${response.status}`;
    console.error(`[Brevo] Échec de l'appel ${path} :`, message);
    console.groupEnd();
    throw new Error(message);
  }

  console.groupEnd();
  return data;
}

async function testAccount() {
  console.log('[testAccount] Démarrage du test de connexion...');
  const apiKey = getApiKey();
  if (!apiKey) {
    console.warn('[testAccount] Aucune clé API saisie, abandon.');
    return;
  }

  setStatus('Test de connexion en cours...');
  clearResult();

  try {
    const account = await callBrevo('/account', apiKey);
    console.log('[testAccount] Succès, compte récupéré :', account);
    setStatus(`Connexion réussie : ${account.email}`);
    renderAccountCard(account);
  } catch (err) {
    console.error('[testAccount] Échec :', err);
    setStatus(`Échec de la connexion : ${err.message}`, true);
    renderError(err.message);
  }
}

async function testContacts() {
  console.log('[testContacts] Démarrage de la récupération des contacts...');
  const apiKey = getApiKey();
  if (!apiKey) {
    console.warn('[testContacts] Aucune clé API saisie, abandon.');
    return;
  }

  setStatus('Récupération des contacts en cours...');
  clearResult();

  try {
    const contacts = await callBrevo('/contacts?', apiKey);
    console.log('[testContacts] Succès, contacts récupérés :', contacts);
    setStatus(`${contacts.count ?? contacts.contacts?.length ?? 0} contact(s) récupéré(s).`);
    renderContactsCards(contacts);
    downloadJson(contacts, 'contacts.json');
  } catch (err) {
    console.error('[testContacts] Échec :', err);
    setStatus(`Échec de la récupération des contacts : ${err.message}`, true);
    renderError(err.message);
  }
}

// function downloadJson(data, filename) {
//   const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
//   const url = URL.createObjectURL(blob);
//   const a = document.createElement('a');
//   a.href = url;
//   a.download = filename;
//   a.click();
//   URL.revokeObjectURL(url);
// }

btnTestAccount.addEventListener('click', testAccount);
btnTestContacts.addEventListener('click', testContacts);
