import { apiGet } from './api.js';

class PlacesSoldChart {
    constructor() {
        this.chartContainer = document.getElementById('chart-places-sold');
        this.countEl = document.getElementById('places-sold-count');
        this.growthEl = document.getElementById('places-sold-growth');
        
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
                this.renderChart(data.sales_history);
            }
        } catch (err) {
            console.error('[PlacesSoldChart] Erreur lors du chargement des stats:', err);
        }
    }

    updateUI(data) {
        if (this.countEl) {
            this.countEl.textContent = Number(data.total_sales || 0).toLocaleString('fr-FR');
        }
        if (this.growthEl) {
            this.growthEl.textContent = '+5%'; // Placeholder pour l'instant
        }
    }

    renderChart(history) {
        if (!history || history.length === 0) {
            this.chartContainer.innerHTML = '<div class="flex items-center justify-center h-full text-gray-400">Aucune donnée</div>';
            return;
        }

        this.chartContainer.innerHTML = '';

        const options = {
            series: [{
                name: 'Places Vendues',
                data: history.map(h => parseInt(h.count || 0))
            }],
            chart: {
                type: 'bar',
                height: '100%',
                sparkline: { enabled: true },
                toolbar: { show: false }
            },
            plotOptions: {
                bar: {
                    borderRadius: 4,
                    columnWidth: '60%',
                    colors: {
                        ranges: [{
                            from: 0,
                            to: 1000000,
                            color: '#15277B'
                        }]
                    }
                }
            },
            xaxis: {
                categories: history.map(h => h.month),
                crosshairs: { width: 1 }
            },
            tooltip: {
                fixed: { enabled: false },
                x: { show: true },
                y: {
                    title: {
                        formatter: function () { return ''; }
                    }
                },
                marker: { show: false }
            },
            states: {
                hover: {
                    filter: { type: 'darken', value: 0.9 }
                }
            }
        };

        const chart = new ApexCharts(this.chartContainer, options);
        chart.render();
    }
}

document.addEventListener('DOMContentLoaded', () => {
    new PlacesSoldChart();
});
