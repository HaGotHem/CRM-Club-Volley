// Affiche le nombre total de contacts Brevo sur le Dashboard.
// Réutilise la clé API saisie sur la page "Fiche contact / test api brevo"
// (stockée dans le localStorage par testapi.js).

const BREVO_BASE_URL = 'https://api.brevo.com/v3';
const totalEl = document.getElementById('total-contacts');

function getApiKey() {
  return localStorage.getItem('brevoApiKey');
}

async function brevoGet(path) {
  const apiKey = getApiKey();
  if (!apiKey) {
    throw new Error('Clé API Brevo manquante. Renseignez-la depuis la page "Fiche contact".');
  }

  const response = await fetch(`${BREVO_BASE_URL}${path}`, {
    method: 'GET',
    headers: {
      accept: 'application/json',
      'api-key': apiKey,
    },
  });

  const data = await response.json().catch(() => null);

  if (!response.ok) {
    const message = data?.message || `Erreur HTTP ${response.status}`;
    throw new Error(message);
  }

  return data;
}

async function afficherTotalContacts() {
  if (!totalEl) return;

  try {
    const res = await brevoGet('/contacts?limit=1');
    const total = res.count ?? 0;
    console.log('Nombre de contacts :', total);
    totalEl.textContent = total;
  } catch (err) {
    console.error('[total-contact] Échec de la récupération du total :', err);
    totalEl.textContent = 'Erreur';
  }
}

async function afficherTotalGroupes() {
  const totalGroupesEl = document.getElementById('total-groupes');
  if (!totalGroupesEl) return;

  try {
    const res = await brevoGet('/contacts/lists?limit=50');
    const total = res.count ?? 0;
    console.log('Nombre de groupes :', total);
    console.log(res.lists);
    totalGroupesEl.textContent = total;
  } catch (err) {
    console.error('[total-groupes] Échec de la récupération du total :', err);
    totalGroupesEl.textContent = 'Erreur';
  }
}

afficherTotalContacts();
afficherTotalGroupes();
