// Graphiques de la page Statistiques (valeurs fictives en attendant le backend).
// Même approche que Charts.js du Dashboard : Chart.js est chargé via CDN (global `Chart`).

const principal  = '#15277B';
const secondaire = '#F5D804';
const sky        = '#38BDF8';
const grayLight  = '#E5E7EB';

const percentLabel = (ctx) => ` ${ctx.label} : ${ctx.parsed}%`;

// --- Camembert : mails envoyés vs manquants ---
const mailsCanvas = document.getElementById('mailsChart');
if (mailsCanvas) {
  new Chart(mailsCanvas, {
    type: 'pie',
    data: {
      labels: ['Mails envoyés', 'Manquantes'],
      datasets: [{
        data: [67.5, 32.5],
        backgroundColor: [principal, secondaire],
        borderColor: '#ffffff',
        borderWidth: 2,
      }],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { position: 'bottom', labels: { color: principal, font: { weight: 'bold' } } },
        tooltip: { callbacks: { label: percentLabel } },
      },
    },
  });
}

// --- Anneau : répartition invitations / places vendues ---
const invitationsCanvas = document.getElementById('invitationsChart');
if (invitationsCanvas) {
  new Chart(invitationsCanvas, {
    type: 'doughnut',
    data: {
      labels: ["Nombre d'invitations", 'Places vendues'],
      datasets: [{
        data: [76.9, 23.1],
        backgroundColor: [grayLight, principal],
        borderWidth: 0,
      }],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      cutout: '70%',
      plugins: {
        legend: { display: false },
        tooltip: { callbacks: { label: percentLabel } },
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
      labels: ['Sep', 'Oct', 'Nov', 'Déc', 'Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août'],
      datasets: [{
        data: [30, 28, 35, 33, 45, 42, 48, 52, 50, 58, 62, 68],
        borderColor: sky,
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
          ticks: { color: sky, font: { weight: 'bold' } },
        },
      },
    },
  });
}
