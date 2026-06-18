import { apiGet, apiPost, apiDelete } from './api.js';

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
        
        // Nouveaux éléments pour la pagination et la liste
        this.contactsListEl = document.getElementById('contacts-list');
        this.btnPrecedent = document.getElementById('btn-precedent');
        this.btnSuivant = document.getElementById('btn-suivant');
        this.pageInfo = document.getElementById('page-info');
        this.contactsTitle = document.getElementById('contacts-title');
        this.searchInput = document.getElementById('search-contacts');
        this.btnTransferer = document.getElementById('btn-transferer');
        this.btnSupprimerListe = document.getElementById('btn-supprimer-liste');
        this.modalSuppression = document.getElementById('modal-suppression-liste');
        this.confirmSupprimerListe = document.getElementById('confirm-supprimer-liste');
        this.nomListeASupprimer = document.getElementById('nom-liste-a-supprimer');
        this.modalSuccessSync = document.getElementById('modal-success-sync');

        this.currentPage = 1;
        this.currentLimit = 20;
        this.searchQuery = '';
        this.searchTimeout = null;
        
        // Gestion du segment passé en URL
        const urlParams = new URLSearchParams(window.location.search);
        this.currentListId = urlParams.has('segment_id') ? parseInt(urlParams.get('segment_id')) : null;
        this.currentListName = 'Tous les contacts';
        
        this.init();
    }

    /**
     * Initialisation
     */
    async init() {
        console.log('[ContactManager] Initialisation...');
        this.updateStatus('Chargement...', 'bg-yellow-400');
        
        try {
            this.setupEventListeners();
            
            await Promise.all([
                this.loadStats(),
                this.loadGroups(),
                this.loadContacts()
            ]);
            
            this.updateStatus('Base de données locale', 'bg-green-500');
        } catch (err) {
            console.error('[ContactManager] Erreur init :', err);
            this.updateStatus('Erreur base de données', 'bg-red-500');
        }
    }

    /**
     * Configuration des écouteurs d'événements
     */
    setupEventListeners() {
        if (this.btnTransferer) {
            this.btnTransferer.addEventListener('click', async () => {
                if (!this.currentListId) return;

                const originalText = this.btnTransferer.textContent;
                this.btnTransferer.disabled = true;
                this.btnTransferer.innerHTML = '<span class="loading loading-spinner loading-xs mr-2"></span> Transfert...';

                try {
                    const res = await apiPost(`/segments/${this.currentListId}/sync-brevo`, {});
                    if (res.success) {
                        if (this.modalSuccessSync) {
                            this.modalSuccessSync.checked = true;
                        } else {
                            alert('Synchronisation réussie !');
                        }
                        await this.loadGroups(); // Pour mettre à jour l'icône de statut
                        await this.loadContacts(); // Pour mettre à jour le texte du bouton
                    }
                } catch (err) {
                    console.error('[ContactManager] Erreur sync Brevo:', err);
                    alert('Erreur lors de la synchronisation : ' + err.message);
                } finally {
                    this.btnTransferer.disabled = false;
                    this.btnTransferer.textContent = originalText;
                }
            });
        }

        if (this.btnSupprimerListe) {
            this.btnSupprimerListe.addEventListener('click', () => {
                if (!this.currentListId) return;
                if (this.nomListeASupprimer) this.nomListeASupprimer.textContent = this.currentListName;
                if (this.modalSuppression) this.modalSuppression.checked = true;
            });
        }

        if (this.confirmSupprimerListe) {
            this.confirmSupprimerListe.addEventListener('click', async () => {
                if (!this.currentListId) return;

                this.confirmSupprimerListe.disabled = true;
                this.confirmSupprimerListe.innerHTML = '<span class="loading loading-spinner loading-xs mr-2"></span> Suppression...';

                try {
                    const res = await apiDelete(`/segments/${this.currentListId}`);
                    if (res.success) {
                        if (this.modalSuppression) this.modalSuppression.checked = false;
                        
                        // Retour à "Tous les contacts"
                        this.currentListId = null;
                        this.currentListName = 'Tous les contacts';
                        this.currentBrevoId = null;
                        this.currentPage = 1;
                        
                        await this.loadGroups();
                        await this.loadContacts();
                    }
                } catch (err) {
                    console.error('[ContactManager] Erreur suppression segment:', err);
                    alert('Erreur lors de la suppression : ' + err.message);
                } finally {
                    this.confirmSupprimerListe.disabled = false;
                    this.confirmSupprimerListe.textContent = 'Confirmer la suppression';
                }
            });
        }

        if (this.btnPrecedent) {
            this.btnPrecedent.addEventListener('click', () => {
                if (this.currentPage > 1) {
                    this.currentPage--;
                    this.loadContacts();
                }
            });
        }

        if (this.btnSuivant) {
            this.btnSuivant.addEventListener('click', () => {
                this.currentPage++;
                this.loadContacts();
            });
        }

        if (this.searchInput) {
            this.searchInput.addEventListener('input', (e) => {
                this.searchQuery = e.target.value;
                this.currentPage = 1; // Reset à la première page lors d'une recherche
                
                // Debounce pour éviter trop d'appels API
                clearTimeout(this.searchTimeout);
                this.searchTimeout = setTimeout(() => {
                    this.loadContacts();
                }, 400);
            });
        }

        // Écouteurs pour les filtres (si toujours présents ou utilisés par ailleurs)
        document.querySelectorAll('[data-filtre]').forEach(filter => {
            filter.addEventListener('change', (e) => {
                const type = e.target.dataset.filtre;
                
                // Si on coche alpha, on décoche recent et vice-versa
                if (e.target.checked) {
                    if (type === 'alpha') {
                        const recent = document.querySelector('[data-filtre="recent"]');
                        if (recent) recent.checked = false;
                    } else if (type === 'recent') {
                        const alpha = document.querySelector('[data-filtre="alpha"]');
                        if (alpha) alpha.checked = false;
                    }
                }
                
                this.loadContacts(); 
            });
        });
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
     * Charge les stats globales
     */
    async loadStats() {
        try {
            const res = await apiGet('/stats/dashboard');
            if (res.success && res.data) {
                const d = res.data;
                if (this.totalEl) this.totalEl.textContent = Number(d.total_contacts).toLocaleString('fr-FR');
                if (this.totalGroupesEl) this.totalGroupesEl.textContent = d.segment_count;
            }
        } catch (e) {
            console.warn('[ContactManager] Impossible de charger les stats via backend.');
        }
    }

    /**
     * Charge les groupes/segments réels de notre DB
     */
    async loadGroups() {
        const listContainer = document.getElementById('groupes-list');
        if (!listContainer) return;

        try {
            const res = await apiGet('/segments');
            
            if (res.success && res.data) {
                const groups = res.data;
                listContainer.innerHTML = '';
                
                // Option "Tous les contacts"
                const allBtn = document.createElement('button');
                allBtn.className = `flex items-center justify-between w-full p-3 ${!this.currentListId ? 'bg-principal text-white shadow-md' : 'bg-gray-50'} hover:bg-principal hover:text-white rounded-2xl transition-all group text-left mb-2`;
                allBtn.innerHTML = `<span class="font-medium">Tous les contacts</span>`;
                allBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.currentListId = null;
                    this.currentListName = 'Tous les contacts';
                    this.currentBrevoId = null;
                    this.currentPage = 1;
                    this.loadGroups(); 
                    this.loadContacts();
                });
                listContainer.appendChild(allBtn);

                groups.forEach(group => {
                    const isSelected = this.currentListId === group.id;
                    if (isSelected) {
                        this.currentListName = group.nom_segment; // Mise à jour du nom si sélectionné via URL
                        this.currentBrevoId = group.brevo_id;
                    }
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = `flex items-center justify-between w-full p-3 ${isSelected ? 'bg-principal text-white shadow-md selected-segment' : 'bg-gray-50'} hover:bg-principal hover:text-white rounded-2xl transition-all group text-left`;
                    
                    if (isSelected) {
                        btn.id = `segment-btn-${group.id}`;
                    }

                    const statusIcon = group.brevo_id 
                        ? '<span class="flex-shrink-0 w-2 h-2 rounded-full bg-green-400" title="Synchronisé avec Brevo"></span>' 
                        : '<span class="flex-shrink-0 w-2 h-2 rounded-full bg-gray-300" title="Nouveau segment"></span>';

                    btn.innerHTML = `
                        <span class="font-medium truncate mr-2">${group.nom_segment}</span>
                        ${statusIcon}
                    `;
                    btn.addEventListener('click', (e) => {
                        e.preventDefault();
                        this.currentListId = group.id;
                        this.currentListName = group.nom_segment;
                        this.currentBrevoId = group.brevo_id;
                        this.currentPage = 1;
                        this.loadGroups(); 
                        this.loadContacts();
                    });
                    listContainer.appendChild(btn);
                });

                if (this.groupCountEl) this.groupCountEl.textContent = groups.length;

                // Scrolling vers l'élément sélectionné si nécessaire
                if (this.currentListId) {
                    const selectedBtn = document.getElementById(`segment-btn-${this.currentListId}`);
                    if (selectedBtn) {
                        setTimeout(() => {
                            selectedBtn.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                        }, 100);
                    }
                }
            }
        } catch (err) {
            console.error('[ContactManager] Erreur loadGroups:', err);
            listContainer.innerHTML = '<p class="text-red-500 text-sm p-4">Erreur lors du chargement des groupes.</p>';
        }
    }

    /**
     * Charge les contacts réels de notre DB
     */
    async loadContacts() {
        if (!this.contactsListEl) return;

        if (this.contactsTitle) this.contactsTitle.textContent = this.currentListName;

        // Gestion du bouton de transfert
        if (this.btnTransferer) {
            if (this.currentListId) {
                this.btnTransferer.classList.remove('hidden');
                this.btnTransferer.textContent = this.currentBrevoId ? 'Mettre à jour sur Brevo' : 'Transférer vers Brevo';
            } else {
                this.btnTransferer.classList.add('hidden');
            }
        }

        // Gestion du bouton de suppression
        if (this.btnSupprimerListe) {
            if (this.currentListId) {
                this.btnSupprimerListe.classList.remove('hidden');
            } else {
                this.btnSupprimerListe.classList.add('hidden');
            }
        }

        // Skeleton loading
        this.contactsListEl.innerHTML = `
            <div class="animate-pulse space-y-3">
                <div class="h-16 bg-gray-50 rounded-2xl w-full"></div>
                <div class="h-16 bg-gray-50 rounded-2xl w-full"></div>
                <div class="h-16 bg-gray-50 rounded-2xl w-full"></div>
            </div>
        `;

        try {
            let url = `/contacts?page=${this.currentPage}&limit=${this.currentLimit}`;
            if (this.currentListId) {
                url += `&listId=${this.currentListId}`;
            }
            if (this.searchQuery) {
                url += `&search=${encodeURIComponent(this.searchQuery)}`;
            }

            const res = await apiGet(url);

            if (res.success && res.data) {
                const contacts = res.data;
                const pagination = res.pagination;

                // Tri alphabétique si coché (optionnel car on a maintenant une recherche mais on garde la logique si l'élément existe)
                const isAlpha = document.querySelector('[data-filtre="alpha"]')?.checked;
                const isRecent = document.querySelector('[data-filtre="recent"]')?.checked;

                if (isAlpha) {
                    contacts.sort((a, b) => {
                        const nameA = (a.nom + a.prenom).toUpperCase() || (a.email || '').toUpperCase();
                        const nameB = (b.nom + b.prenom).toUpperCase() || (b.email || '').toUpperCase();
                        return nameA.localeCompare(nameB);
                    });
                } else if (isRecent) {
                    contacts.sort((a, b) => new Date(b.date_creation || 0) - new Date(a.date_creation || 0));
                }

                this.renderContacts(contacts);
                this.updatePagination(pagination);
            }
        } catch (err) {
            console.error('[ContactManager] Erreur loadContacts:', err);
            this.contactsListEl.innerHTML = '<p class="text-red-500 text-sm p-4">Erreur lors du chargement des contacts.</p>';
        }
    }

    /**
     * Affiche les contacts dans la liste
     */
    renderContacts(contacts) {
        if (contacts.length === 0) {
            this.contactsListEl.innerHTML = '<p class="text-gray-400 text-center py-8">Aucun contact trouvé.</p>';
            return;
        }

        this.contactsListEl.innerHTML = '';
        contacts.forEach(contact => {
            const card = document.createElement('div');
            card.className = "flex items-center gap-4 p-4 bg-gray-50 rounded-2xl hover:bg-white hover:shadow-md border border-transparent hover:border-gray-100 transition-all";
            
            const initiales = (contact.prenom?.[0] || '') + (contact.nom?.[0] || '') || '?';
            
            card.innerHTML = `
                <div class="w-12 h-12 rounded-xl bg-principal/10 text-principal flex items-center justify-center font-bold">
                    ${initiales.toUpperCase()}
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="font-bold text-gray-800 truncate">${contact.prenom || ''} ${contact.nom || 'Inconnu'}</h3>
                    <div class="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-3 text-sm text-gray-500">
                        <p class="truncate">${contact.email}</p>
                        ${contact.phone ? `<span class="hidden sm:inline text-gray-300">•</span><p class="truncate">${contact.phone}</p>` : ''}
                    </div>
                </div>
                <div class="hidden sm:block">
                    <span class="px-3 py-1 bg-white border border-gray-100 rounded-full text-xs font-medium text-gray-500">
                        ${contact.date_creation ? new Date(contact.date_creation).toLocaleDateString('fr-FR') : 'N/A'}
                    </span>
                </div>
            `;
            this.contactsListEl.appendChild(card);
        });
    }

    /**
     * Met à jour l'état de la pagination
     */
    updatePagination(pagination) {
        if (!pagination) return;
        
        if (this.pageInfo) {
            this.pageInfo.textContent = `Page ${pagination.current_page} sur ${pagination.total_pages || 1}`;
        }

        if (this.btnPrecedent) {
            this.btnPrecedent.disabled = (pagination.current_page <= 1);
        }

        if (this.btnSuivant) {
            this.btnSuivant.disabled = (pagination.current_page >= pagination.total_pages);
        }
    }

    /* ==================== Export Brevo ==================== */

    /**
     * Ouvre la modale et (re)charge groupes + contacts depuis la BDD.
     */
    openExportModal() {
        this.resetExportResult();
        const selectAllG = document.getElementById('export-select-all-groupes');
        const selectAllC = document.getElementById('export-select-all-contacts');
        if (selectAllG) selectAllG.checked = false;
        if (selectAllC) selectAllC.checked = false;

        if (typeof this.exportModal.showModal === 'function') {
            this.exportModal.showModal();
        } else {
            this.exportModal.setAttribute('open', 'open');
        }

        this.loadExportGroups();
        this.loadExportContacts();
    }

    /**
     * Charge la liste des groupes (segments) dans la modale.
     */
    async loadExportGroups() {
        if (!this.exportGroupesList) return;
        try {
            const res = await apiGet('/segments');
            const groups = (res.success && res.data) ? res.data : [];

            if (groups.length === 0) {
                this.exportGroupesList.innerHTML = '<p class="text-gray-400 text-sm py-4">Aucun groupe disponible.</p>';
                return;
            }

            this.exportGroupesList.innerHTML = '';
            groups.forEach(group => {
                const label = document.createElement('label');
                label.className = 'flex items-center gap-3 p-3 bg-gray-50 rounded-xl hover:bg-gray-100 cursor-pointer transition-colors';
                label.innerHTML = `
                    <input type="checkbox" class="checkbox checkbox-primary checkbox-sm" data-export-segment value="${group.id}" />
                    <span class="font-medium text-sm text-gray-700 truncate">${group.nom_segment}</span>
                `;
                this.exportGroupesList.appendChild(label);
            });

            this.exportGroupesList
                .querySelectorAll('input[data-export-segment]')
                .forEach(cb => cb.addEventListener('change', () => this.updateExportSelectionInfo()));
        } catch (err) {
            console.error('[Export] Erreur loadExportGroups:', err);
            this.exportGroupesList.innerHTML = '<p class="text-red-500 text-sm py-4">Erreur lors du chargement des groupes.</p>';
        }
    }

    /**
     * Charge la liste des contacts dans la modale.
     */
    async loadExportContacts() {
        if (!this.exportContactsList) return;
        try {
            const res = await apiGet('/contacts?page=1&limit=1000');
            this.exportContacts = (res.success && res.data) ? res.data : [];

            if (this.exportContacts.length === 0) {
                this.exportContactsList.innerHTML = '<p class="text-gray-400 text-sm py-4">Aucun contact disponible.</p>';
                return;
            }

            this.exportContactsList.innerHTML = '';
            this.exportContacts.forEach(contact => {
                const label = document.createElement('label');
                label.className = 'export-contact-row flex items-center gap-3 p-2 bg-gray-50 rounded-xl hover:bg-gray-100 cursor-pointer transition-colors';
                const nomComplet = `${contact.prenom || ''} ${contact.nom || ''}`.trim() || 'Inconnu';
                label.dataset.search = `${nomComplet} ${contact.email}`.toLowerCase();
                label.innerHTML = `
                    <input type="checkbox" class="checkbox checkbox-primary checkbox-sm" data-export-contact value="${contact.id}" />
                    <div class="min-w-0">
                        <p class="font-medium text-sm text-gray-700 truncate">${nomComplet}</p>
                        <p class="text-xs text-gray-400 truncate">${contact.email}</p>
                    </div>
                `;
                this.exportContactsList.appendChild(label);
            });

            this.exportContactsList
                .querySelectorAll('input[data-export-contact]')
                .forEach(cb => cb.addEventListener('change', () => this.updateExportSelectionInfo()));
        } catch (err) {
            console.error('[Export] Erreur loadExportContacts:', err);
            this.exportContactsList.innerHTML = '<p class="text-red-500 text-sm py-4">Erreur lors du chargement des contacts.</p>';
        }
    }

    /**
     * Filtre l'affichage des contacts de la modale selon la recherche.
     */
    filterExportContacts(term) {
        const q = (term || '').trim().toLowerCase();
        this.exportContactsList?.querySelectorAll('.export-contact-row').forEach(row => {
            const match = !q || (row.dataset.search || '').includes(q);
            row.classList.toggle('hidden', !match);
            const cb = row.querySelector('input[data-export-contact]');
            if (cb) cb.classList.toggle('hidden-by-search', !match);
        });
    }

    /**
     * Met à jour le compteur de sélection.
     */
    updateExportSelectionInfo() {
        const info = document.getElementById('export-selection-info');
        if (!info) return;
        const nbGroupes = this.exportGroupesList?.querySelectorAll('input[data-export-segment]:checked').length || 0;
        const nbContacts = this.exportContactsList?.querySelectorAll('input[data-export-contact]:checked').length || 0;

        if (nbGroupes === 0 && nbContacts === 0) {
            info.textContent = 'Aucune sélection';
        } else {
            const parts = [];
            if (nbGroupes) parts.push(`${nbGroupes} groupe${nbGroupes > 1 ? 's' : ''}`);
            if (nbContacts) parts.push(`${nbContacts} contact${nbContacts > 1 ? 's' : ''}`);
            info.textContent = `Sélection : ${parts.join(' • ')}`;
        }
    }

    /**
     * Réinitialise le bandeau de résultat.
     */
    resetExportResult() {
        const result = document.getElementById('export-result');
        if (result) {
            result.classList.add('hidden');
            result.innerHTML = '';
        }
    }

    /**
     * Affiche un message de résultat dans la modale.
     */
    showExportResult(message, type = 'info') {
        const result = document.getElementById('export-result');
        if (!result) return;
        const styles = {
            success: 'bg-green-50 text-green-700 border border-green-200',
            error: 'bg-red-50 text-red-700 border border-red-200',
            info: 'bg-blue-50 text-blue-700 border border-blue-200'
        };
        result.className = `text-sm rounded-xl p-3 mb-4 ${styles[type] || styles.info}`;
        result.innerHTML = message;
        result.classList.remove('hidden');
    }

    /**
     * Lance l'export vers Brevo.
     */
    async runExport() {
        const segmentIds = [...(this.exportGroupesList?.querySelectorAll('input[data-export-segment]:checked') || [])]
            .map(cb => parseInt(cb.value, 10));
        const contactIds = [...(this.exportContactsList?.querySelectorAll('input[data-export-contact]:checked') || [])]
            .map(cb => parseInt(cb.value, 10));

        if (segmentIds.length === 0 && contactIds.length === 0) {
            this.showExportResult('Veuillez sélectionner au moins un groupe ou un contact.', 'error');
            return;
        }

        const spinner = document.getElementById('export-spinner');
        if (spinner) spinner.classList.remove('hidden');
        if (this.btnLancerExport) this.btnLancerExport.disabled = true;
        this.resetExportResult();

        try {
            const res = await apiPost('/sync/brevo/export', {
                segment_ids: segmentIds,
                contact_ids: contactIds
            });

            const d = res.data || {};
            this.showExportResult(
                `<strong>Export terminé.</strong><br>
                 Listes créées : ${d.lists_created ?? 0} •
                 Listes réutilisées : ${d.lists_existing ?? 0}<br>
                 Contacts synchronisés : ${d.contacts_synced ?? 0} •
                 Erreurs : ${d.errors ?? 0}`,
                (d.errors ?? 0) > 0 ? 'info' : 'success'
            );

            // Rafraîchit les groupes/contacts de la page (de nouveaux segments ont pu apparaître)
            this.loadGroups();
        } catch (err) {
            console.error('[Export] Erreur runExport:', err);
            this.showExportResult(`Erreur lors de l'export : ${err.message}`, 'error');
        } finally {
            if (spinner) spinner.classList.add('hidden');
            if (this.btnLancerExport) this.btnLancerExport.disabled = false;
        }
    }
}

// Initialisation
document.addEventListener('DOMContentLoaded', () => {
    new ContactManager();
});
