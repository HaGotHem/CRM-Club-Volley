/**
 * Graphiques du Dashboard utilisant Chart.js
 */

const principal = '#15277B';

/**
 * Initialisation du graphique Donut (Répartition)
 */
function initStatsChart() {
    const statsCanvas = document.getElementById('StatsChart');
    if (statsCanvas) {
        new Chart(statsCanvas, {
            type: 'doughnut',
            data: {
                labels: ["Nombre d'invitations", 'Places Vendues'],
                datasets: [{
                    data: [76.9, 23.1],
                    backgroundColor: ['#E5E7EB', principal],
                    borderWidth: 0,
                    hoverOffset: 4
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '75%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(255, 255, 255, 0.9)',
                        titleColor: '#1f2937',
                        bodyColor: '#1f2937',
                        borderColor: '#e5e7eb',
                        borderWidth: 1,
                        padding: 12,
                        callbacks: {
                            label: (ctx) => ` ${ctx.label} : ${ctx.parsed}%`,
                        },
                    },
                },
            },
        });
    }
}

/**
 * Initialisation du graphique de ligne (Affluence)
 */
function initAffluenceChart() {
    const affluenceCanvas = document.getElementById('affluenceChart');
    if (affluenceCanvas) {
        new Chart(affluenceCanvas, {
            type: 'line',
            data: {
                labels: ['Sep', 'Oct', 'Nov', 'Déc', 'Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Aout'],
                datasets: [{
                    label: 'Affluence',
                    data: [10, 28, 22, 30, 45, 42, 40, 48, 44, 38, 60, 62],
                    borderColor: '#38BDF8',
                    backgroundColor: 'rgba(56, 189, 248, 0.1)',
                    borderWidth: 3,
                    tension: 0.4,
                    pointRadius: 4,
                    pointBackgroundColor: '#38BDF8',
                    fill: true,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { 
                    legend: { display: false },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                    }
                },
                scales: {
                    y: { 
                        display: false, 
                        beginAtZero: true 
                    },
                    x: {
                        grid: { display: false },
                        border: { display: false },
                        ticks: { 
                            color: '#94a3b8', 
                            font: { 
                                size: 10,
                                weight: '500' 
                            } 
                        },
                    },
                },
            },
        });
    }
}

// Lancement de l'initialisation au chargement du DOM
document.addEventListener('DOMContentLoaded', () => {
    initStatsChart();
    initAffluenceChart();
});
