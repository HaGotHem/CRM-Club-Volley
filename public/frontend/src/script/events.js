document.addEventListener('DOMContentLoaded', async () => {
    const eventsListCurrent = document.getElementById('events-list-current');
    const eventsListPast = document.getElementById('events-list-past');
    const seasonsTabs = document.getElementById('seasons-tabs');
    const currentCount = document.getElementById('current-count');

    // Chargement initial
    await loadInitialData();

    async function loadInitialData() {
        try {
            const response = await fetch('/api/events');
            const result = await response.json();

            if (result.success) {
                renderSection(eventsListCurrent, result.data.current);
                currentCount.textContent = `${result.data.current.length} événements`;
                
                renderSeasonTabs(result.data.past_seasons);
            } else {
                const errorHtml = `<div class="p-8 text-center text-red-500 bg-red-50 rounded-2xl border border-red-100">${result.error}</div>`;
                eventsListCurrent.innerHTML = errorHtml;
            }
        } catch (error) {
            console.error('Erreur:', error);
            eventsListCurrent.innerHTML = '<div class="p-8 text-center text-red-500 bg-red-50 rounded-2xl border border-red-100">Erreur lors de la récupération des événements.</div>';
        }
    }

    function renderSeasonTabs(seasons) {
        if (!seasons || seasons.length === 0) {
            seasonsTabs.innerHTML = '<span class="p-2 text-xs text-gray-400">Aucune saison passée.</span>';
            return;
        }

        seasonsTabs.innerHTML = seasons.map(season => `
            <button class="tab tab-sm md:tab-md rounded-lg season-tab" data-season="${season}">${season}</button>
        `).join('');

        seasonsTabs.querySelectorAll('.season-tab').forEach(tab => {
            tab.addEventListener('click', async (e) => {
                // UI: Active tab
                seasonsTabs.querySelectorAll('.season-tab').forEach(t => t.classList.remove('tab-active', 'bg-principal', 'text-white'));
                e.currentTarget.classList.add('tab-active', 'bg-principal', 'text-white');

                const season = e.currentTarget.getAttribute('data-season');
                await loadSeasonEvents(season);
            });
        });
    }

    async function loadSeasonEvents(season) {
        eventsListPast.innerHTML = `
            <div class="animate-pulse space-y-4">
                <div class="h-24 bg-gray-50 rounded-2xl w-full"></div>
                <div class="h-24 bg-gray-50 rounded-2xl w-full"></div>
            </div>
        `;

        try {
            const response = await fetch(`/api/events?season=${encodeURIComponent(season)}`);
            const result = await response.json();

            if (result.success) {
                renderSection(eventsListPast, result.data);
            } else {
                eventsListPast.innerHTML = `<div class="p-8 text-center text-red-500">${result.error}</div>`;
            }
        } catch (error) {
            console.error('Erreur:', error);
            eventsListPast.innerHTML = '<div class="p-8 text-center text-red-500">Erreur lors du chargement de la saison.</div>';
        }
    }

    function renderSection(container, events) {
        if (!events || events.length === 0) {
            container.innerHTML = '<div class="p-12 text-center text-gray-400">Aucun événement trouvé.</div>';
            return;
        }

        container.innerHTML = events.map(event => {
            // Weezevent renvoie parfois la date avec un fuseau horaire ou un format que le constructeur Date 
            // peut mal interpréter selon le navigateur. On nettoie si besoin.
            // Ex: "2026-06-18 21:37:00"
            const dateStr = event.date.replace(' ', 'T');
            const dateObj = new Date(dateStr);
            
            const formattedDate = isNaN(dateObj.getTime()) ? event.date : dateObj.toLocaleDateString('fr-FR', {
                weekday: 'long', day: 'numeric', month: 'long', year: 'numeric',
                hour: '2-digit', minute: '2-digit'
            });

            // Logique de couleur pour les événements absents en DB
            const cardBg = event.in_db ? 'bg-white' : 'bg-amber-50';
            const borderCol = event.in_db ? 'border-gray-100' : 'border-amber-200';
            
            return `
                <div class="${cardBg} ${borderCol} border rounded-2xl p-6 transition-all hover:shadow-md flex flex-col md:flex-row md:items-center justify-between gap-6">
                    <div class="flex items-start gap-4">
                        <div class="w-12 h-12 rounded-xl flex items-center justify-center ${event.in_db ? 'bg-blue-50 text-blue-600' : 'bg-amber-100 text-amber-600'}">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-bold text-gray-800 text-lg line-clamp-1">${event.nom_evenement || event.nom}</h3>
                            <p class="text-sm text-gray-500 flex items-center gap-2">
                                <span class="capitalize">${formattedDate}</span>
                                <span>•</span>
                                <span>${event.lieu || '—'}</span>
                            </p>
                            <div class="flex items-center gap-2 mt-2">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase ${event.in_db ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700'}">
                                    ${event.in_db ? 'Synchronisé' : 'Disponible sur Weezevent'}
                                </span>
                                ${event.sales_status && event.sales_status.libelle_status ? `
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-blue-100 text-blue-700">
                                        ${event.sales_status.libelle_status}
                                    </span>
                                ` : ''}
                                ${event.archived_weezevent ? '<span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-gray-100 text-gray-600">Archivé Weezevent</span>' : ''}
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-6">
                        <div class="text-right">
                            <p class="text-[10px] uppercase tracking-wider text-gray-400 font-bold leading-none mb-1">Tickets vendus</p>
                            <p class="text-xl font-black text-principal">${event.total_tickets || 0}</p>
                        </div>
                        
                        ${!event.in_db ? `
                            <button class="btn btn-sm bg-principal hover:bg-principal/90 text-white border-none rounded-xl px-4 normal-case btn-import" data-id="${event.id}">
                                <span class="loading loading-spinner loading-xs hidden"></span>
                                Importer les données
                            </button>
                        ` : `
                             <button class="btn btn-sm btn-ghost text-gray-400 rounded-xl px-4 normal-case cursor-default pointer-events-none">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                                </svg>
                                À jour
                            </button>
                        `}
                    </div>
                </div>
            `;
        }).join('');

        // Attacher les events listeners (utilisant délégation d'événements ou après chaque render)
        container.querySelectorAll('.btn-import').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                const eventId = e.currentTarget.getAttribute('data-id');
                await importEvent(eventId, e.currentTarget);
            });
        });
    }

    async function importEvent(id, button) {
        const spinner = button.querySelector('.loading');
        
        try {
            button.disabled = true;
            spinner.classList.remove('hidden');

            const response = await fetch(`/api/events/import/${id}`, {
                method: 'POST'
            });
            const result = await response.json();

            if (result.success) {
                // Afficher le modal
                document.getElementById('import-details').textContent = 
                    `${result.stats.contacts} contacts et ${result.stats.tickets} billets importés pour cet événement.`;
                document.getElementById('modal-success-import').checked = true;
                
                // Recharger la liste
                if (button.closest('#events-list-current')) {
                    await loadInitialData();
                } else {
                    const activeTab = seasonsTabs.querySelector('.tab-active');
                    if (activeTab) {
                        const season = activeTab.getAttribute('data-season');
                        await loadSeasonEvents(season);
                    }
                    // On rafraîchit aussi le haut au cas où
                    await loadInitialData();
                }
            } else {
                alert(`Erreur d'import : ${result.error}`);
            }
        } catch (error) {
            console.error('Erreur:', error);
            alert("Erreur lors de l'import de l'événement.");
        } finally {
            button.disabled = false;
            spinner.classList.add('hidden');
        }
    }
});
