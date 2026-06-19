// Courbe d'affluence — stats.html.twig
// Données : GET /api/stats/affluence → { labels, values }
// Librairie : ApexCharts (CDN chargé dans le layout)

async function renderAffluenceChart() {
    const container = document.querySelector('#affluence-chart');
    const skeleton  = document.querySelector('#affluence-skeleton');
    if (!container) return;

    try {
        const res  = await fetch('/api/stats/affluence');
        const json = await res.json();

        if (!json.success) throw new Error(json.error);

        const { labels, values } = json.data;

        const options = {
            series: [{ name: 'Spectateurs', data: values }],
            chart: {
                type: 'area',
                height: 190,
                toolbar:    { show: false },
                zoom:       { enabled: false },
                background: 'transparent',
                animations: { enabled: true, speed: 600 },
            },
            colors: ['#15277B'],
            fill: {
                type: 'gradient',
                gradient: {
                    shadeIntensity: 1,
                    opacityFrom: 0.25,
                    opacityTo:   0,
                    stops: [0, 100],
                }
            },
            stroke: { curve: 'smooth', width: 4, colors: ['#f1f5f9'] },
            xaxis: {
                categories: labels,
                labels: {
                    style: {
                        colors:     Array(labels.length).fill('rgba(255,255,255,0.4)'),
                        fontSize:   '10px',
                        fontWeight: 700,
                        fontFamily: 'inherit',
                    }
                },
                axisBorder: { show: false },
                axisTicks:  { show: false },
            },
            yaxis:      { show: false },
            grid:       { show: false, padding: { left: 0, right: 0, top: 0, bottom: 0 } },
            dataLabels: { enabled: false },
            markers:    { size: 0 },
            tooltip: {
                theme: 'dark',
                y: { formatter: val => val + ' spectateurs' },
            },
        };

        // Masquer le squelette, afficher le graphique
        if (skeleton)  skeleton.classList.add('hidden');
        container.classList.remove('hidden');

        new ApexCharts(container, options).render();

    } catch (err) {
        console.error('[stats-affluence]', err);
        if (skeleton) skeleton.classList.add('hidden');
        container.classList.remove('hidden');
        container.innerHTML = '<p class="text-white/40 text-sm text-center mt-8">Aucune donnée disponible</p>';
    }
}

if (typeof ApexCharts !== 'undefined') {
    renderAffluenceChart();
} else {
    window.addEventListener('load', renderAffluenceChart);
}