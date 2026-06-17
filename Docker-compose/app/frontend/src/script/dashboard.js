// Remplit les compteurs du Dashboard avec les vraies données du backend.
// Source : GET /api/stats/dashboard (Slim -> PostgreSQL).
import { apiGet } from './api.js';

function setText(id, value) {
  const el = document.getElementById(id);
  if (el) el.textContent = value;
}

async function loadDashboard() {
  try {
    const res = await apiGet('/stats/dashboard');
    const d = res.data || {};

    setText('total-contacts', Number(d.total_contacts ?? 0).toLocaleString('fr-FR'));
    setText('total-groupes',  d.segment_count ?? 0);
    setText('invites-count',  Number(d.invited_count ?? 0).toLocaleString('fr-FR'));
    setText('places-count',   Number(d.tickets_sold ?? 0).toLocaleString('fr-FR'));

    console.log('[dashboard] Stats chargées depuis le backend :', d);
  } catch (err) {
    console.error('[dashboard] Échec du chargement des stats :', err);
  }
}

loadDashboard();
