import { apiPost } from './api.js';

const weezsync = document.getElementById('btn-sync-WeezEvent');
const brevosync = document.getElementById('btn-sync-Brevo');

weezsync.addEventListener('click', async () => {
    console.log('[syncWeezEvent] Démarrage de la synchronisation...');
    try {
        const res = await apiPost('/sync/weezevent', {});
        console.log('[syncWeezEvent] Terminé', res);
    } catch (err) {
        console.error('[syncWeezEvent] Erreur :', err);
    }
});

brevosync.addEventListener('click', async () => {
    console.log('[syncBrevo] Démarrage de la synchronisation...');
    try {
        const res = await apiPost('/sync/brevo', {});
        console.log('[syncBrevo] Terminé', res);
    } catch (err) {
        console.error('[syncBrevo] Erreur :', err);
    }
});
