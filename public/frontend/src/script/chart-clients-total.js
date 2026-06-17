import { apiGet } from './api.js';

class ClientsChart {
    constructor() {
        this.chartContainer = document.getElementById('chart-clients-total');
        this.countEl = document.getElementById('clients-total-count');
        this.growthEl = document.getElementById('clients-growth');
        
        if (this.chartContainer) {
            this.init();
        }
    }

    async init() {
        try {
            const res = await apiGet('/stats/dashboard');
            if (res.success && res.data) {
                const data = res.data;
                this.updateUI(data);
                this.renderChart(data.history);
            }
        } catch (err) {
            console.error('[ClientsChart] Erreur lors du chargement des stats:', err);
        }
    }

    updateUI(data) {
        if (this.countEl) {
            this.countEl.textContent = Number(data.total_contacts).toLocaleString('fr-FR');
        }
        
        // Mise à jour des autres stats globales de la page
        const statsCards = document.querySelectorAll('.stats-card');
        
        if (statsCards.length >= 2) {
            // Première carte : Supporters Totaux
            const totalVal = statsCards[0].querySelector('.text-3xl.font-black');
            if (totalVal) totalVal.textContent = Number(data.total_contacts).toLocaleString('fr-FR');

            // Deuxième carte : Nouveaux ce mois
            const newVal = statsCards[1].querySelector('.text-3xl.font-black');
            if (newVal) newVal.textContent = Number(data.new_contacts_7days).toLocaleString('fr-FR');
        }

        if (this.growthEl) {
            this.growthEl.textContent = '+0%'; 
        }
    }

    renderChart(history) {
        // On vide le placeholder
        this.chartContainer.innerHTML = '';

        if (!history || history.length === 0) {
            this.chartContainer.innerHTML = '<div class="flex items-center justify-center h-full text-gray-400">Aucune donnée disponible</div>';
            return;
        }

        const options = {
            series: [{
                name: 'Contacts',
                data: history.map(h => parseInt(h.count || 0))
            }],
            chart: {
                type: 'area',
                height: 250,
                toolbar: { show: false },
                sparkline: { enabled: false }
            },
            colors: ['#15277B'],
            fill: {
                type: 'gradient',
                gradient: {
                    shadeIntensity: 1,
                    opacityFrom: 0.45,
                    opacityTo: 0.05,
                    stops: [20, 100, 100, 100]
                }
            },
            dataLabels: { enabled: false },
            stroke: {
                curve: 'smooth',
                width: 3
            },
            xaxis: {
                categories: history.map(h => h.month),
                axisBorder: { show: false },
                axisTicks: { show: false },
                labels: {
                    style: {
                        colors: '#94a3b8',
                        fontSize: '12px'
                    }
                }
            },
            yaxis: { show: false },
            grid: { show: false },
            tooltip: {
                x: { format: 'dd/MM/yy HH:mm' },
            },
        };

        const chart = new ApexCharts(this.chartContainer, options);
        chart.render();
    }
}

document.addEventListener('DOMContentLoaded', () => {
    new ClientsChart();
});
