import { apiGet } from './api.js';

/**
 * Calendrier des événements + statistiques par période sur la page Stats.
 * - Affiche tous les événements (point sous le jour concerné).
 * - Permet de sélectionner une plage de dates (2 clics) ou un raccourci.
 * - Charge les stats agrégées de la période via /api/stats/period.
 */
class StatsCalendar {
    constructor() {
        this.grid = document.getElementById('cal-grid');
        if (!this.grid) return; // pas sur la page stats

        this.monthLabel = document.getElementById('cal-month-label');
        this.eventsList = document.getElementById('period-events-list');
        this.rangeLabel = document.getElementById('period-range-label');
        this.periodBadge = document.getElementById('period-badge');

        const today = new Date();
        this.viewYear = today.getFullYear();
        this.viewMonth = today.getMonth(); // 0-11

        this.rangeStart = null;   // 'YYYY-MM-DD'
        this.rangeEnd = null;     // 'YYYY-MM-DD'
        this.rangeComplete = false;

        this.eventsByDay = new Map(); // 'YYYY-MM-DD' -> nb événements
        this.allEvents = [];

        this.MONTHS = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin',
                       'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

        this.init();
    }

    async init() {
        this.bindEvents();
        await this.loadEvents();
        this.render();
        // Sélection par défaut : le mois courant
        this.applyPreset('month');
    }

    bindEvents() {
        document.getElementById('cal-prev')?.addEventListener('click', () => this.changeMonth(-1));
        document.getElementById('cal-next')?.addEventListener('click', () => this.changeMonth(1));

        document.querySelectorAll('[data-preset]').forEach(btn => {
            btn.addEventListener('click', () => this.applyPreset(btn.dataset.preset));
        });

        // Délégation des clics sur les jours
        this.grid.addEventListener('click', (e) => {
            const cell = e.target.closest('[data-date]');
            if (cell) this.onDayClick(cell.dataset.date);
        });
    }

    /* ----------------------- Données ----------------------- */

    async loadEvents() {
        try {
            const res = await apiGet('/events');
            this.allEvents = (res.success && res.data) ? res.data : [];
            this.eventsByDay.clear();
            this.allEvents.forEach(ev => {
                const key = this.dayKey(ev.date);
                this.eventsByDay.set(key, (this.eventsByDay.get(key) || 0) + 1);
            });
        } catch (err) {
            console.error('[StatsCalendar] Erreur chargement événements:', err);
        }
    }

    async loadPeriodStats() {
        if (!this.rangeStart || !this.rangeEnd) return;
        try {
            const res = await apiGet(`/stats/period?start=${this.rangeStart}&end=${this.rangeEnd}`);
            if (res.success && res.data) {
                this.renderStats(res.data);
            }
        } catch (err) {
            console.error('[StatsCalendar] Erreur stats période:', err);
        }
    }

    /* ----------------------- Helpers dates ----------------------- */

    /** Extrait 'YYYY-MM-DD' d'une date SQL ('YYYY-MM-DD HH:MM:SS'). */
    dayKey(dateStr) {
        return String(dateStr).slice(0, 10);
    }

    /** Formate un objet Date local en 'YYYY-MM-DD'. */
    fmt(date) {
        const y = date.getFullYear();
        const m = String(date.getMonth() + 1).padStart(2, '0');
        const d = String(date.getDate()).padStart(2, '0');
        return `${y}-${m}-${d}`;
    }

    /** 'YYYY-MM-DD' -> affichage 'JJ/MM/AAAA'. */
    prettyDate(key) {
        if (!key) return '—';
        const [y, m, d] = key.split('-');
        return `${d}/${m}/${y}`;
    }

    /* ----------------------- Navigation ----------------------- */

    changeMonth(delta) {
        this.viewMonth += delta;
        if (this.viewMonth < 0) { this.viewMonth = 11; this.viewYear--; }
        if (this.viewMonth > 11) { this.viewMonth = 0; this.viewYear++; }
        this.render();
    }

    /* ----------------------- Sélection ----------------------- */

    onDayClick(dateKey) {
        if (!this.rangeStart || this.rangeComplete) {
            // Nouveau départ
            this.rangeStart = dateKey;
            this.rangeEnd = null;
            this.rangeComplete = false;
        } else {
            // Fermeture de la plage
            this.rangeEnd = dateKey;
            if (this.rangeEnd < this.rangeStart) {
                [this.rangeStart, this.rangeEnd] = [this.rangeEnd, this.rangeStart];
            }
            this.rangeComplete = true;
        }
        this.render();
        if (this.rangeComplete) {
            this.updateRangeLabels();
            this.loadPeriodStats();
        } else if (this.rangeLabel) {
            this.rangeLabel.textContent = `Début : ${this.prettyDate(this.rangeStart)} — sélectionnez la fin`;
        }
    }

    applyPreset(preset) {
        const today = new Date();
        let start, end;

        switch (preset) {
            case 'month':
                start = new Date(this.viewYear, this.viewMonth, 1);
                end = new Date(this.viewYear, this.viewMonth + 1, 0);
                break;
            case '30d':
                end = today;
                start = new Date(today);
                start.setDate(start.getDate() - 29);
                break;
            case 'season': {
                // Saison sportive : 1er sept -> 31 août
                const y = today.getMonth() >= 8 ? today.getFullYear() : today.getFullYear() - 1;
                start = new Date(y, 8, 1);
                end = new Date(y + 1, 7, 31);
                break;
            }
            case 'all':
            default:
                if (this.allEvents.length > 0) {
                    const keys = this.allEvents.map(e => this.dayKey(e.date)).sort();
                    const [sy, sm, sd] = keys[0].split('-').map(Number);
                    const [ey, em, ed] = keys[keys.length - 1].split('-').map(Number);
                    start = new Date(sy, sm - 1, sd);
                    end = new Date(ey, em - 1, ed);
                } else {
                    start = end = today;
                }
                break;
        }

        this.rangeStart = this.fmt(start);
        this.rangeEnd = this.fmt(end);
        this.rangeComplete = true;

        // On positionne le calendrier sur le mois de début
        this.viewYear = start.getFullYear();
        this.viewMonth = start.getMonth();

        this.render();
        this.updateRangeLabels();
        this.loadPeriodStats();
    }

    updateRangeLabels() {
        const txt = `Du ${this.prettyDate(this.rangeStart)} au ${this.prettyDate(this.rangeEnd)}`;
        if (this.rangeLabel) this.rangeLabel.textContent = txt;
        if (this.periodBadge) this.periodBadge.textContent = `Période : ${txt}`;
    }

    /* ----------------------- Rendu ----------------------- */

    render() {
        if (this.monthLabel) {
            this.monthLabel.textContent = `${this.MONTHS[this.viewMonth]} ${this.viewYear}`;
        }

        const firstDay = new Date(this.viewYear, this.viewMonth, 1);
        const startOffset = (firstDay.getDay() + 6) % 7; // Lundi = 0
        const daysInMonth = new Date(this.viewYear, this.viewMonth + 1, 0).getDate();
        const todayKey = this.fmt(new Date());

        this.grid.innerHTML = '';

        // Cases vides avant le 1er du mois
        for (let i = 0; i < startOffset; i++) {
            const empty = document.createElement('div');
            this.grid.appendChild(empty);
        }

        for (let day = 1; day <= daysInMonth; day++) {
            const key = `${this.viewYear}-${String(this.viewMonth + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            const hasEvent = this.eventsByDay.has(key);
            const inRange = this.rangeStart && this.rangeEnd && key >= this.rangeStart && key <= this.rangeEnd;
            const isBound = key === this.rangeStart || (this.rangeComplete && key === this.rangeEnd);

            const cell = document.createElement('button');
            cell.type = 'button';
            cell.dataset.date = key;

            let classes = 'relative h-10 rounded-xl text-sm flex items-center justify-center transition-colors cursor-pointer ';
            if (isBound) {
                classes += 'bg-principal text-white font-bold ';
            } else if (inRange) {
                classes += 'bg-principal/15 text-principal ';
            } else {
                classes += 'hover:bg-gray-100 text-gray-700 ';
            }
            if (key === todayKey && !isBound) {
                classes += 'ring-1 ring-principal/40 ';
            }
            cell.className = classes.trim();

            const dot = hasEvent
                ? '<span class="absolute bottom-1 left-1/2 -translate-x-1/2 w-1.5 h-1.5 rounded-full bg-secondaire"></span>'
                : '';
            cell.innerHTML = `<span>${day}</span>${dot}`;

            this.grid.appendChild(cell);
        }
    }

    renderStats(data) {
        const set = (id, val) => {
            const el = document.getElementById(id);
            if (el) el.textContent = val;
        };

        set('kpi-events', Number(data.events_count || 0).toLocaleString('fr-FR'));
        set('kpi-tickets', Number(data.tickets_sold || 0).toLocaleString('fr-FR'));
        set('kpi-invitations', Number(data.invitations || 0).toLocaleString('fr-FR'));
        set('kpi-attendees', Number(data.attendees || 0).toLocaleString('fr-FR'));
        set('kpi-new-contacts', Number(data.new_contacts || 0).toLocaleString('fr-FR'));
        set('kpi-revenue', `${Number(data.revenue || 0).toLocaleString('fr-FR', { maximumFractionDigits: 0 })} €`);

        this.renderEventsList(data.events || []);
    }

    renderEventsList(events) {
        if (!this.eventsList) return;

        if (events.length === 0) {
            this.eventsList.innerHTML = '<p class="text-gray-400 text-sm py-4">Aucun événement sur cette période.</p>';
            return;
        }

        this.eventsList.innerHTML = '';
        events.forEach(ev => {
            const row = document.createElement('div');
            row.className = 'flex items-center gap-3 p-3 bg-gray-50 rounded-2xl';
            row.innerHTML = `
                <div class="w-11 shrink-0 text-center">
                    <p class="text-[10px] font-bold text-gray-400 uppercase">${this.MONTHS[parseInt(this.dayKey(ev.date).slice(5, 7), 10) - 1].slice(0, 3)}</p>
                    <p class="text-lg font-black text-principal leading-none">${this.dayKey(ev.date).slice(8, 10)}</p>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="font-bold text-sm text-gray-800 truncate">${ev.nom}</p>
                    <p class="text-xs text-gray-400 truncate">${ev.lieu || '—'}</p>
                </div>
                <span class="px-2 py-1 bg-white border border-gray-100 rounded-full text-xs font-bold text-principal shrink-0">
                    ${Number(ev.tickets || 0).toLocaleString('fr-FR')} billets
                </span>
            `;
            this.eventsList.appendChild(row);
        });
    }
}

document.addEventListener('DOMContentLoaded', () => {
    new StatsCalendar();
});
