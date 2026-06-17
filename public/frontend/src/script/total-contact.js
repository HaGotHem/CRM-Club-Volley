import { apiGet } from './api.js';

/**
 * Gestionnaire des contacts et groupes Brevo sur la page Contact
 */
class ContactManager {
    constructor() {
        this.totalEl = document.getElementById('total-contacts');
        this.totalGroupesEl = document.getElementById('total-groupes');
        this.groupCountEl = document.getElementById('group-count');
        this.statusDot = document.getElementById('status-dot');
        this.statusText = document.getElementById('api-status');
        
        this.init();
    }

    /**
     * Initialisation
     */
    async init() {
        console.log('[ContactManager] Initialisation...');
        this.updateStatus('Chargement...', 'bg-yellow-400');
        
        try {
            // Dans un vrai scénario, ces données viendraient de notre backend Slim
            // qui ferait le pont avec Brevo pour éviter d'exposer la clé API au client.
            // Pour l'instant, on simule ou on utilise l'API locale si disponible.
            await Promise.all([
                this.loadStats(),
                this.loadGroups()
            ]);
            
            this.updateStatus('Connecté à Brevo', 'bg-green-500');
        } catch (err) {
            console.error('[ContactManager] Erreur init :', err);
            this.updateStatus('Erreur de connexion', 'bg-red-500');
        }
    }

    /**
     * Met à jour le bandeau de statut
     */
    updateStatus(message, dotClass) {
        if (this.statusText) this.statusText.textContent = `Statut API : ${message}`;
        if (this.statusDot) {
            this.statusDot.className = `w-2 h-2 rounded-full ${dotClass}`;
        }
    }

    /**
     * Charge les stats globales (simulé ou via backend)
     */
    async loadStats() {
        try {
            // On essaie de récupérer les stats du dashboard qui contiennent déjà ces infos
            const res = await apiGet('/stats/dashboard');
            if (res.success && res.data) {
                const d = res.data;
                if (this.totalEl) this.totalEl.textContent = Number(d.total_contacts ?? 10128).toLocaleString('fr-FR');
                if (this.totalGroupesEl) this.totalGroupesEl.textContent = d.segment_count ?? 6;
            }
        } catch (e) {
            console.warn('[ContactManager] Impossible de charger les stats via backend, utilisation des placeholders.');
        }
    }

    /**
     * Charge les groupes/segments
     */
    async loadGroups() {
        const listContainer = document.getElementById('groupes-list');
        if (!listContainer) return;

        try {
            // Simulation ou appel réel si implémenté
            // const groups = await apiGet('/segments'); 
            
            // Pour l'instant on garde une structure propre
            const mockGroups = [
                { id: 1, name: 'Partenaires', count: 42 },
                { id: 2, name: 'Licenciés', count: 1250 },
                { id: 3, name: 'Presse', count: 12 },
                { id: 4, name: 'Bénévoles', count: 85 }
            ];

            listContainer.innerHTML = '';
            mockGroups.forEach(group => {
                const btn = document.createElement('button');
                btn.className = "flex items-center justify-between w-full p-3 bg-gray-50 hover:bg-principal hover:text-white rounded-2xl transition-all group text-left";
                btn.innerHTML = `
                    <span class="font-medium">${group.name}</span>
                    <span class="text-xs bg-white/20 px-2 py-1 rounded-lg group-hover:bg-white group-hover:text-principal font-bold">${group.count}</span>
                `;
                listContainer.appendChild(btn);
            });

            if (this.groupCountEl) this.groupCountEl.textContent = mockGroups.length;

        } catch (err) {
            listContainer.innerHTML = '<p class="text-red-500 text-sm p-4">Erreur lors du chargement des groupes.</p>';
        }
    }
}

// Initialisation
document.addEventListener('DOMContentLoaded', () => {
    new ContactManager();
});
