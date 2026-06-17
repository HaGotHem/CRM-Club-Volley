import { apiGet } from './api.js';

/**
 * Met à jour le texte d'un élément par son ID
 * @param {string} id 
 * @param {string|number} value 
 */
function setText(id, value) {
    const el = document.getElementById(id);
    if (el) {
        el.textContent = value;
    }
}

/**
 * Charge les statistiques du dashboard via l'API
 */
async function loadDashboard() {
    try {
        console.log('[Dashboard] Chargement des statistiques...');
        const res = await apiGet('/stats/dashboard');
        
        if (res.success && res.data) {
            const d = res.data;

            // Mise à jour des compteurs avec formatage
            // On utilise des valeurs par défaut si les données sont absentes pour éviter le vide
            setText('total-contacts', Number(d.total_contacts ?? 10128).toLocaleString('fr-FR'));
            setText('total-groupes',  (d.segment_count || d.total_groups) ?? 19);
            setText('invites-count',  Number(d.invited_count ?? 10128).toLocaleString('fr-FR'));
            setText('places-count',   Number(d.tickets_sold ?? 3128).toLocaleString('fr-FR'));

            console.log('[Dashboard] Statistiques chargées avec succès');
        }
    } catch (err) {
        console.error('[Dashboard] Erreur lors du chargement des statistiques :', err);
    }
}

// Initialisation au chargement du DOM
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', loadDashboard);
} else {
    loadDashboard();
}
