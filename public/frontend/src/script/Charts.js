/**
 * Graphiques du Dashboard utilisant Chart.js
 */

const principal = '#15277B';

/**
 * Initialisation du graphique Donut (Répartition)
 */
function initStatsChart(data) {
    const statsCanvas = document.getElementById('StatsChart');
    if (statsCanvas) {
        const total = (data.invited_count || 0) + (data.paid_sales || 0);
        const invitedPct = total > 0 ? ((data.invited_count / total) * 100).toFixed(1) : 0;
        const paidPct = total > 0 ? ((data.paid_sales / total) * 100).toFixed(1) : 0;

        // Mise à jour de la légende textuelle à côté du chart
        const legendPaid = document.getElementById('legend-paid-pct');
        const legendInvited = document.getElementById('legend-invited-pct');
        if (legendPaid) legendPaid.textContent = `${paidPct}%`;
        if (legendInvited) legendInvited.textContent = `${invitedPct}%`;

        new Chart(statsCanvas, {
            type: 'doughnut',
            data: {
                labels: ["Nombre d'invitations", 'Places Vendues'],
                datasets: [{
                    data: [invitedPct, paidPct],
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
function initAffluenceChart(salesHistory) {
    const affluenceCanvas = document.getElementById('affluenceChart');
    if (affluenceCanvas) {
        const labels = salesHistory ? salesHistory.map(h => h.month) : ['Sep', 'Oct', 'Nov', 'Déc', 'Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Aout'];
        
        const salesData = salesHistory ? salesHistory.map(h => h.sales) : [10, 28, 22, 30, 45, 42, 40, 48, 44, 38, 60, 62];
        const invitedData = salesHistory ? salesHistory.map(h => h.invitations) : [5, 10, 8, 15, 20, 15, 12, 18, 14, 10, 25, 30];
        const totalData = salesHistory ? salesHistory.map(h => h.total) : [15, 38, 30, 45, 65, 57, 52, 66, 58, 48, 85, 92];

        new Chart(affluenceCanvas, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Ventes',
                        data: salesData,
                        borderColor: principal,
                        backgroundColor: 'transparent',
                        borderWidth: 3,
                        tension: 0.4,
                        pointRadius: 4,
                        pointBackgroundColor: principal,
                        fill: false,
                    },
                    {
                        label: 'Invitations',
                        data: invitedData,
                        borderColor: '#94a3b8', // Gray
                        backgroundColor: 'transparent',
                        borderWidth: 2,
                        tension: 0.4,
                        pointRadius: 0,
                        fill: false,
                        borderDash: [5, 5]
                    },
                    {
                        label: 'Total',
                        data: totalData,
                        borderColor: '#38BDF8', // Sky blue
                        backgroundColor: 'rgba(56, 189, 248, 0.1)',
                        borderWidth: 4,
                        tension: 0.4,
                        pointRadius: 5,
                        pointBackgroundColor: '#38BDF8',
                        fill: true,
                    }
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { 
                    legend: { 
                        display: true,
                        position: 'top',
                        align: 'end',
                        labels: {
                            usePointStyle: true,
                            boxWidth: 6,
                            font: { size: 10, weight: '600' }
                        }
                    },
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

export { initStatsChart, initAffluenceChart };
