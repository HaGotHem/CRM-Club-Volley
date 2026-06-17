const DashStatsPieChart = document.getElementById('StatsChart');

new Chart(DashStatsPieChart, {
    type: 'pie',
    data: {
        labels: ['Places Vendues', 'Invitations'],
        datasets: [{
            label: 'Statistiques',
            data: [67,33],
            borderWidth: 1,
            backgroundColor: [
                'rgba(255, 99, 132, 0.2)',
                'rgba(54, 162, 235, 0.2)',
            ]
        }]
    },
    options: {
        responsive: true
    }
});
