import { apiGet } from './api.js';

/**
 * Sélecteur d'événement + statistiques sur la page Stats.
 * On choisit un événement (ou « Tous les événements ») dans la liste déroulante,
 * et les KPI se mettent à jour via /api/stats/event/{id}.
 */
class StatsEvents {
    constructor() {
        this.select = document.getElementById('event-select');
        if (!this.select) return; // pas sur la page stats

        this.detailsEl = document.getElementById('event-details');
        this.subtitleEl = document.getElementById('stats-subtitle');
        this.badge = document.getElementById('period-badge');

        this.events = [];
        this.tarifChart = null;
        this.MONTHS = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin',
                       'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];

        this.init();
    }

    async init() {
        this.select.addEventListener('change', () => this.onSelect());
        await this.loadEvents();
        this.onSelect(); // charge « Tous les événements » par défaut
    }

    async loadEvents() {
        try {
            const res = await apiGet('/events');

            // /api/events peut renvoyer un tableau, ou { current: [...], past: [...] }
            let raw = [];
            if (res.success && res.data) {
                raw = Array.isArray(res.data)
                    ? res.data
                    : [...(res.data.current || []), ...(res.data.past || [])];
            }

            // Normalisation (les champs diffèrent selon que l'événement vient de la DB ou de Weezevent)
            this.events = raw.map(e => ({
                id:      e.id ?? e.idevenementweezevent ?? e.idEvenementWeezevent ?? 0,
                nom:     e.nom ?? e.nom_evenement ?? 'Événement',
                date:    e.date ?? '',
                lieu:    e.lieu ?? '',
                type:    e.type ?? '',
                tickets: e.total_tickets ?? e.tickets ?? 0,
            })).filter(e => e.id);

            // Plus récents en premier
            this.events.sort((a, b) => new Date(b.date) - new Date(a.date));

            this.events.forEach(ev => {
                const opt = document.createElement('option');
                opt.value = ev.id;
                opt.textContent = `${this.fmtDate(ev.date)} — ${ev.nom}`;
                this.select.appendChild(opt);
            });
        } catch (err) {
            console.error('[StatsEvents] Erreur chargement événements:', err);
        }
    }

    async onSelect() {
        const id = parseInt(this.select.value, 10) || 0;
        try {
            const res = await apiGet(`/stats/event/${id}`);
            if (res.success && res.data) {
                this.render(res.data, id);
            }
        } catch (err) {
            console.error('[StatsEvents] Erreur stats événement:', err);
        }
    }

    render(d, id) {
        const set = (eid, val) => {
            const el = document.getElementById(eid);
            if (el) el.textContent = val;
        };

        set('kpi-tickets', Number(d.tickets_sold || 0).toLocaleString('fr-FR'));
        set('kpi-invitations', Number(d.invitations || 0).toLocaleString('fr-FR'));
        set('kpi-attendees', Number(d.attendees || 0).toLocaleString('fr-FR'));
        set('kpi-total', Number(d.total_tickets || 0).toLocaleString('fr-FR'));
        set('kpi-revenue', `${Number(d.revenue || 0).toLocaleString('fr-FR', { maximumFractionDigits: 0 })} €`);

        if (id > 0) {
            const ev = this.events.find(e => e.id === id) || {};
            const title = ev.nom || 'Événement';
            if (this.subtitleEl) this.subtitleEl.textContent = title;
            if (this.badge) this.badge.textContent = title;
            this.renderDetails([
                ['Date', this.fmtDateLong(ev.date)],
                ['Lieu', ev.lieu && ev.lieu !== '—' ? ev.lieu : 'Non renseigné'],
                ['Type', ev.type && ev.type !== '—' ? ev.type : 'Non renseigné'],
                ['Billets total', Number(d.total_tickets || 0).toLocaleString('fr-FR')],
            ]);
        } else {
            const nb = d.event?.events_count ?? this.events.length;
            const subtitle = 'Tous les événements';
            if (this.subtitleEl) this.subtitleEl.textContent = subtitle;
            if (this.badge) this.badge.textContent = subtitle;
            this.renderDetails([
                ['Événements', Number(nb).toLocaleString('fr-FR')],
                ['Billets total', Number(d.total_tickets || 0).toLocaleString('fr-FR')],
            ]);
        }

        this.renderTarifChart(d.tarifs || []);
    }

    /**
     * Graphique en barres horizontales : nombre de billets par tarif (top 10).
     */
    renderTarifChart(tarifs) {
        const el = document.getElementById('tarif-chart');
        if (!el) return;

        const top = [...tarifs].sort((a, b) => b.count - a.count).slice(0, 10);

        if (this.tarifChart) {
            this.tarifChart.destroy();
            this.tarifChart = null;
        }
        el.innerHTML = '';

        if (top.length === 0 || typeof ApexCharts === 'undefined') {
            el.innerHTML = '<p class="text-gray-400 text-sm py-6 text-center">Aucun billet sur cette sélection.</p>';
            return;
        }

        // Libellés tronqués pour l'axe (nom complet conservé dans l'infobulle)
        const labels = top.map(t => {
            const name = t.tarif || '—';
            return name.length > 30 ? name.slice(0, 29) + '…' : name;
        });

        this.tarifChart = new ApexCharts(el, {
            series: [{ name: 'Billets', data: top.map(t => t.count) }],
            chart: { type: 'bar', height: Math.max(200, top.length * 38), toolbar: { show: false }, fontFamily: 'inherit' },
            colors: ['#15277B'],
            plotOptions: { bar: { horizontal: true, borderRadius: 6, barHeight: '62%' } },
            dataLabels: { enabled: true, style: { colors: ['#fff'], fontWeight: 700, fontSize: '11px' } },
            xaxis: { categories: labels, labels: { style: { colors: '#94a3b8' } } },
            yaxis: { labels: { style: { colors: '#475569', fontSize: '12px' } } },
            grid: { borderColor: '#f1f5f9' },
            tooltip: {
                y: {
                    formatter: (val, opts) => {
                        const t = top[opts.dataPointIndex] || {};
                        const rev = Number(t.revenue || 0).toLocaleString('fr-FR', { maximumFractionDigits: 0 });
                        return `${val} billets — ${rev} €`;
                    }
                }
            }
        });
        this.tarifChart.render();
    }

    renderDetails(rows) {
        if (!this.detailsEl) return;
        this.detailsEl.innerHTML = rows.map(([label, value]) => `
            <div class="flex items-center justify-between gap-3 border-b border-gray-50 pb-2">
                <span class="text-gray-400 font-medium">${label}</span>
                <span class="text-gray-800 font-bold text-right truncate">${value}</span>
            </div>
        `).join('');
    }

    /** 'YYYY-MM-DD HH:MM:SS' -> 'JJ mois' */
    fmtDate(dateStr) {
        const key = String(dateStr).slice(0, 10);
        const [, m, d] = key.split('-');
        return `${parseInt(d, 10)} ${this.MONTHS[parseInt(m, 10) - 1] || ''}`;
    }

    /** 'YYYY-MM-DD HH:MM:SS' -> 'JJ mois AAAA' */
    fmtDateLong(dateStr) {
        if (!dateStr) return 'Non renseignée';
        const key = String(dateStr).slice(0, 10);
        const [y, m, d] = key.split('-');
        return `${parseInt(d, 10)} ${this.MONTHS[parseInt(m, 10) - 1] || ''} ${y}`;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    new StatsEvents();
});
