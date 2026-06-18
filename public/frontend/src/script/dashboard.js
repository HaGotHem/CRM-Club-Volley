import { apiGet, apiPost } from './api.js';
import { initStatsChart, initAffluenceChart } from './Charts.js';

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
 * Gère la synchronisation Brevo
 */
async function setupSync() {
    const btnSync = document.getElementById('btn-sync-brevo');
    const modal = document.getElementById('modal-sync-brevo');
    const closeBtn = document.getElementById('btn-sync-close');
    const xBtn = document.getElementById('modal-sync-close');

    const el = {
        status: document.getElementById('sync-status-text'),
        progress: document.getElementById('sync-progress'),
        count: document.getElementById('sync-count'),
        percent: document.getElementById('sync-percent'),
        contacts_created: document.getElementById('sync-contacts-created'),
        contacts_updated: document.getElementById('sync-contacts-updated'),
        events_created: document.getElementById('sync-events-created'),
        events_updated: document.getElementById('sync-events-updated'),
        tickets_created: document.getElementById('sync-tickets-created'),
        tickets_updated: document.getElementById('sync-tickets-updated'),
        links: document.getElementById('sync-links'),
        errors: document.getElementById('sync-errors'),
    };

    function resetUi(total = 0) {
        if (!el.progress) return;
        el.status.textContent = 'Initialisation…';
        el.progress.value = 0;
        el.count.textContent = `0 / ${total}`;
        el.percent.textContent = '0%';
        if (el.contacts_created) el.contacts_created.textContent = '0';
        if (el.contacts_updated) el.contacts_updated.textContent = '0';
        if (el.events_created)   el.events_created.textContent   = '0';
        if (el.events_updated)   el.events_updated.textContent   = '0';
        if (el.tickets_created)  el.tickets_created.textContent  = '0';
        if (el.tickets_updated)  el.tickets_updated.textContent  = '0';
        if (el.links)            el.links.textContent            = '0';
        if (el.errors)           el.errors.textContent           = '0';
        if (closeBtn) closeBtn.setAttribute('disabled', 'disabled');
        if (xBtn) xBtn.setAttribute('disabled', 'disabled');
    }

    function updateUi(done, total, s) {
        const pct = total > 0 ? Math.min(100, Math.round((done / total) * 100)) : 0;
        el.progress.value = pct;
        el.count.textContent = `${Math.min(done, total)} / ${total}`;
        el.percent.textContent = `${pct}%`;
        if (s) {
            if (el.contacts_created) el.contacts_created.textContent = String(s.contacts_created_acc || 0);
            if (el.contacts_updated) el.contacts_updated.textContent = String(s.contacts_updated_acc || 0);
            if (el.events_created)   el.events_created.textContent   = String(s.events_created_acc || 0);
            if (el.events_updated)   el.events_updated.textContent   = String(s.events_updated_acc || 0);
            if (el.tickets_created)  el.tickets_created.textContent  = String(s.tickets_created_acc || 0);
            if (el.tickets_updated)  el.tickets_updated.textContent  = String(s.tickets_updated_acc || 0);
            if (el.links)            el.links.textContent            = String(s.links_created_acc || 0);
            if (el.errors)           el.errors.textContent           = String(s.errors_acc || 0);
        }
    }

    if (!btnSync) return;

    // Fermeture modale
    if (closeBtn) closeBtn.addEventListener('click', () => { try { modal?.close(); } catch(_) {} });
    if (xBtn) xBtn.addEventListener('click', () => { try { modal?.close(); } catch(_) {} });

    btnSync.addEventListener('click', async () => {
        const originalContent = btnSync.innerHTML;
        try {
            // Prépare UI
            btnSync.disabled = true;
            btnSync.innerHTML = `<span class="loading loading-spinner"></span> Préparation…`;
            
            // Mise à jour du titre de la modale pour Brevo
            const modalTitle = modal?.querySelector('h3');
            if (modalTitle) modalTitle.textContent = 'Synchronisation Brevo en cours…';
            
            try { modal?.showModal(); } catch(_) {}

            // Récupérer le total global
            el.status.textContent = 'Récupération du nombre total de contacts…';
            const countRes = await apiGet('/sync/brevo/count');
            const total = Number(countRes.total || 0);
            resetUi(total);

            // S'il n'y a rien à synchroniser, on lance quand même un petit job pour les listes
            const batchSize = 500;
            let offset = 0;
            let processed = 0;
            const acc = { 
                contacts_created_acc: 0, 
                contacts_updated_acc: 0, 
                events_created_acc: 0,
                events_updated_acc: 0,
                tickets_created_acc: 0,
                tickets_updated_acc: 0,
                links_created_acc: 0, 
                errors_acc: 0 
            };

            if (total === 0) {
                el.status.textContent = 'Aucun contact à traiter (synchronisation des listes)…';
                const r = await apiPost('/sync/brevo/import', { offset: 0, limit: 1 });
                acc.contacts_created_acc += r.data.contacts_created || 0;
                acc.contacts_updated_acc += r.data.contacts_updated || 0;
                acc.links_created_acc    += r.data.links_created    || 0;
                acc.errors_acc           += r.data.errors           || 0;
                updateUi(0, 0, acc);
            } else {
                // Boucle par lots
                while (processed < total) {
                    el.status.textContent = `Synchronisation des contacts… (${processed + 1} → ${Math.min(processed + batchSize, total)})`;
                    const res = await apiPost('/sync/brevo/import', { offset, limit: batchSize });

                    acc.contacts_created_acc += res.data.contacts_created || 0;
                    acc.contacts_updated_acc += res.data.contacts_updated || 0;
                    acc.links_created_acc    += res.data.links_created    || 0;
                    acc.errors_acc           += res.data.errors           || 0;

                    processed = Math.min(total, processed + batchSize);
                    offset += batchSize;
                    updateUi(processed, total, acc);
                }
            }

            // Terminé
            el.status.textContent = 'Synchronisation terminée';
            if (closeBtn) closeBtn.removeAttribute('disabled');
            if (xBtn) xBtn.removeAttribute('disabled');

            await loadDashboard();
            btnSync.disabled = false;
            btnSync.innerHTML = originalContent;
        } catch (err) {
            console.error('[Dashboard] Erreur synchro :', err);
            el.status.textContent = 'Erreur lors de la synchronisation';
            if (closeBtn) closeBtn.removeAttribute('disabled');
            if (xBtn) xBtn.removeAttribute('disabled');
            btnSync.disabled = false;
            btnSync.innerHTML = originalContent;
            // Pas de reload brutal, on laisse l'user fermer la modale pour voir les compteurs
        }
    });
}

/**
 * Met à jour l'indicateur de tendance (badge +/- %)
 * @param {string} id ID de l'élément
 * @param {number} value Valeur du pourcentage
 */
function setTrend(id, value) {
    const el = document.getElementById(id);
    if (!el) return;

    const isPositive = value >= 0;
    const absValue = Math.abs(value);
    
    el.textContent = `${isPositive ? '+' : '-'}${absValue}%`;
    
    // Classes CSS selon le signe
    if (isPositive) {
        el.className = 'text-sm font-bold bg-green-50 text-green-500 px-2 py-1 rounded-lg';
    } else {
        el.className = 'text-sm font-bold bg-red-50 text-red-500 px-2 py-1 rounded-lg';
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
            setText('total-contacts', Number(d.total_contacts ?? 0).toLocaleString('fr-FR'));
            setText('total-groupes',  Number(d.total_groups ?? 0).toLocaleString('fr-FR'));
            setText('invites-count',  Number(d.invited_count ?? 0).toLocaleString('fr-FR'));
            setText('places-count',   Number(d.paid_sales ?? 0).toLocaleString('fr-FR'));

            // Tendances
            if (d.trends) {
                setTrend('trend-contacts', d.trends.contacts);
                setTrend('trend-groupes', d.trends.groups);
                setTrend('trend-invitations', d.trends.invitations);
                setTrend('trend-sales', d.trends.sales);
            }

            // Graphiques
            if (typeof initStatsChart === 'function') {
                initStatsChart(d);
            }
            if (typeof initAffluenceChart === 'function') {
                initAffluenceChart(d.sales_history);
            }

            // Comparaison des ventes
            if (d.sales_history && d.sales_history.length >= 2) {
                const thisMonth = d.sales_history[d.sales_history.length - 1].count;
                const lastMonth = d.sales_history[d.sales_history.length - 2].count;
                
                setText('sales-this-month', thisMonth.toLocaleString('fr-FR'));
                setText('sales-last-month', lastMonth.toLocaleString('fr-FR'));

                // Mise à jour des barres
                const maxSales = Math.max(thisMonth, lastMonth, 1);
                const heightThis = (thisMonth / maxSales) * 100;
                const heightLast = (lastMonth / maxSales) * 100;

                const barThis = document.getElementById('bar-this-month');
                const barLast = document.getElementById('bar-last-month');
                if (barThis) barThis.style.height = `${Math.max(heightThis * 1.8, 10)}px`;
                if (barLast) barLast.style.height = `${Math.max(heightLast * 1.8, 10)}px`;

                // Carte de performance
                const diff = thisMonth - lastMonth;
                const pct = lastMonth > 0 ? Math.round((diff / lastMonth) * 100) : (thisMonth > 0 ? 100 : 0);
                
                const perfCard = document.getElementById('performance-card');
                const perfTitle = document.getElementById('performance-title');
                const perfDesc = document.getElementById('performance-desc');

                if (perfCard && perfTitle && perfDesc) {
                    if (diff >= 0) {
                        perfCard.className = 'p-6 bg-blue-50 rounded-3xl border border-blue-100 transition-colors duration-300';
                        perfTitle.className = 'text-blue-800 font-semibold mb-2 flex items-center gap-2';
                        perfTitle.innerHTML = `<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg> Performance en hausse`;
                        perfDesc.textContent = `Vos ventes ont augmenté de ${pct}% par rapport au mois dernier. Continuez ainsi !`;
                    } else {
                        perfCard.className = 'p-6 bg-orange-50 rounded-3xl border border-orange-100 transition-colors duration-300';
                        perfTitle.className = 'text-orange-800 font-semibold mb-2 flex items-center gap-2';
                        perfTitle.innerHTML = `<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"></path></svg> Attention à la baisse`;
                        perfDesc.textContent = `Vos ventes ont diminué de ${Math.abs(pct)}% par rapport au mois dernier. Analysez vos campagnes !`;
                    }
                }
            }

            console.log('[Dashboard] Statistiques chargées avec succès');
        }
    } catch (err) {
        console.error('[Dashboard] Erreur lors du chargement des statistiques :', err);
    }
}

async function setupSyncWeezevent() {
    const btnSync = document.getElementById('btn-sync-weezevent');
    const modal = document.getElementById('modal-sync-brevo'); // on réutilise la même modale
    const closeBtn = document.getElementById('btn-sync-close');
    const xBtn = document.getElementById('modal-sync-close');

    const el = {
        status: document.getElementById('sync-status-text'),
        progress: document.getElementById('sync-progress'),
        count: document.getElementById('sync-count'),
        percent: document.getElementById('sync-percent'),
        contacts_created: document.getElementById('sync-contacts-created'),
        contacts_updated: document.getElementById('sync-contacts-updated'),
        events_created: document.getElementById('sync-events-created'),
        events_updated: document.getElementById('sync-events-updated'),
        tickets_created: document.getElementById('sync-tickets-created'),
        tickets_updated: document.getElementById('sync-tickets-updated'),
        links: document.getElementById('sync-links'),
        errors: document.getElementById('sync-errors'),
    };

    function resetUi(total = 0) {
        if (!el.progress) return;
        el.status.textContent = 'Initialisation…';
        el.progress.value = 0;
        el.count.textContent = `0 / ${total}`;
        el.percent.textContent = '0%';
        if (el.contacts_created) el.contacts_created.textContent = '0';
        if (el.contacts_updated) el.contacts_updated.textContent = '0';
        if (el.events_created)   el.events_created.textContent   = '0';
        if (el.events_updated)   el.events_updated.textContent   = '0';
        if (el.tickets_created)  el.tickets_created.textContent  = '0';
        if (el.tickets_updated)  el.tickets_updated.textContent  = '0';
        if (el.links)            el.links.textContent            = '0';
        if (el.errors)           el.errors.textContent           = '0';
        if (closeBtn) closeBtn.setAttribute('disabled', 'disabled');
        if (xBtn) xBtn.setAttribute('disabled', 'disabled');
    }

    function updateUi(done, total, s) {
        const pct = total > 0 ? Math.min(100, Math.round((done / total) * 100)) : 0;
        el.progress.value = pct;
        el.count.textContent = `${Math.min(done, total)} / ${total}`;
        el.percent.textContent = `${pct}%`;
        if (s) {
            if (el.contacts_created) el.contacts_created.textContent = String(s.contacts_created_acc || 0);
            if (el.contacts_updated) el.contacts_updated.textContent = String(s.contacts_updated_acc || 0);
            if (el.events_created)   el.events_created.textContent   = String(s.events_created_acc || 0);
            if (el.events_updated)   el.events_updated.textContent   = String(s.events_updated_acc || 0);
            if (el.tickets_created)  el.tickets_created.textContent  = String(s.tickets_created_acc || 0);
            if (el.tickets_updated)  el.tickets_updated.textContent  = String(s.tickets_updated_acc || 0);
            if (el.links)            el.links.textContent            = String(s.links_created_acc || 0);
            if (el.errors)           el.errors.textContent           = String(s.errors_acc || 0);
        }
    }

    if (!btnSync) return;

    if (closeBtn) closeBtn.addEventListener('click', () => { try { modal?.close(); } catch(_) {} });
    if (xBtn) xBtn.addEventListener('click', () => { try { modal?.close(); } catch(_) {} });

    btnSync.addEventListener('click', async () => {
        const originalContent = btnSync.innerHTML;
        try {
            btnSync.disabled = true;
            btnSync.innerHTML = `<span class="loading loading-spinner"></span> Préparation…`;
            
            // Mise à jour du titre de la modale pour Weezevent
            const modalTitle = modal?.querySelector('h3');
            if (modalTitle) modalTitle.textContent = 'Synchronisation Weezevent en cours…';
            
            try { modal?.showModal(); } catch(_) {}

            el.status.textContent = 'Récupération du nombre total d’éléments Weezevent…';
            const countRes = await apiGet('/sync/weezevent/count');
            const total = Number(countRes.total || 0);
            resetUi(total);

            const batchSize = 500;
            let offset = 0;
            let processed = 0;
            const acc = { 
                contacts_created_acc: 0, 
                contacts_updated_acc: 0, 
                events_created_acc: 0,
                events_updated_acc: 0,
                tickets_created_acc: 0,
                tickets_updated_acc: 0,
                links_created_acc: 0, 
                errors_acc: 0 
            };

            if (total === 0) {
                el.status.textContent = 'Aucune donnée Weezevent à traiter…';
                updateUi(0, 0, acc);
            } else {
                while (processed < total) {
                    el.status.textContent = `Synchronisation Weezevent… (${processed + 1} → ${Math.min(processed + batchSize, total)})`;
                    const res = await apiPost('/sync/weezevent/import', { offset, limit: batchSize });

                    acc.contacts_created_acc += res.data.contacts_created || 0;
                    acc.contacts_updated_acc += res.data.contacts_updated || 0;
                    acc.events_created_acc   += res.data.events_created   || 0;
                    acc.events_updated_acc   += res.data.events_updated   || 0;
                    acc.tickets_created_acc  += res.data.tickets_created  || 0;
                    acc.tickets_updated_acc  += res.data.tickets_updated  || 0;
                    acc.links_created_acc    += res.data.links_created    || 0;
                    acc.errors_acc           += res.data.errors           || 0;

                    processed = Math.min(total, processed + batchSize);
                    offset += batchSize;
                    updateUi(processed, total, acc);
                }
            }

            el.status.textContent = 'Synchronisation Weezevent terminée';
            if (closeBtn) closeBtn.removeAttribute('disabled');
            if (xBtn) xBtn.removeAttribute('disabled');

            await loadDashboard();
            btnSync.disabled = false;
            btnSync.innerHTML = originalContent;
        } catch (err) {
            console.error('[Dashboard] Erreur synchro Weezevent :', err);
            el.status.textContent = 'Erreur lors de la synchronisation Weezevent';
            if (closeBtn) closeBtn.removeAttribute('disabled');
            if (xBtn) xBtn.removeAttribute('disabled');
            btnSync.disabled = false;
            btnSync.innerHTML = originalContent;
        }
    });
}

// Initialisation au chargement du DOM
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        loadDashboard();
        setupSync();
        setupSyncWeezevent();
    });
} else {
    loadDashboard();
    setupSync();
    setupSyncWeezevent();
}
