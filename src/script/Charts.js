// Graphiques du Dashboard (valeurs fictives en attendant les vraies données).

const principal = '#15277B';

// --- Donut : répartition invitations / places vendues ---
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
      }],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      cutout: '65%',
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            label: (ctx) => `${ctx.label} : ${ctx.parsed}%`,
          },
        },
      },
    },
  });
}

// --- Courbe : taux d'affluence ---
const affluenceCanvas = document.getElementById('affluenceChart');
if (affluenceCanvas) {
  new Chart(affluenceCanvas, {
    type: 'line',
    data: {
      labels: ['Sep', 'Oct', 'Nov', 'Déc', 'Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'juil', 'Aout', 'Oct', 'Nov'],
      datasets: [{
        data: [10, 28, 22, 30, 45, 42, 40, 48, 44, 38, 60, 62, 66, 68],
        borderColor: '#38BDF8',
        borderWidth: 3,
        tension: 0.4,
        pointRadius: 0,
        fill: false,
      }],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        y: { display: false, beginAtZero: true },
        x: {
          grid: { display: false },
          border: { display: false },
          ticks: { color: '#38BDF8', font: { weight: 'bold' } },
        },
      },
    },
  });
}
