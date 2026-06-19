// Courbe d'affluence — stats.html.twig
// Données : GET /api/stats/affluence → { labels, values }
// Librairie : ApexCharts (CDN chargé dans le layout)

async function renderAffluenceCharts() {
    const affluenceContainer = document.querySelector('#affluence-chart');
    const affluenceSkeleton  = document.querySelector('#affluence-skeleton');
    const revenueContainer   = document.querySelector('#revenue-chart');
    const revenueSkeleton    = document.querySelector('#revenue-skeleton');

    if (!affluenceContainer && !revenueContainer) return;

    try {
        const res  = await fetch('/api/stats/affluence');
        const json = await res.json();

        if (!json.success) throw new Error(json.error);

        const { labels, tickets, revenues } = json.data;

        // Configuration commune pour les graphiques
        const commonOptions = {
            chart: {
                type: 'area',
                height: 250,
                toolbar:    { show: false },
                zoom:       { enabled: false },
                background: 'transparent',
                animations: { enabled: true, speed: 600 },
            },
            stroke: { curve: 'smooth', width: 4 },
            xaxis: {
                categories: labels,
                labels: {
                    show: false,
                    rotate: -45,
                    rotateAlways: false,
                    hideOverlappingLabels: true,
                    style: {
                        colors:     'rgba(255,255,255,0.8)',
                        fontSize:   '10px',
                        fontWeight: 500,
                        fontFamily: 'inherit',
                    }
                },
                axisBorder: { show: true, color: 'rgba(255,255,255,0.2)' },
                axisTicks:  { show: true, color: 'rgba(255,255,255,0.2)' },
                tooltip:    { enabled: false }
            },
            yaxis: { 
                show: true,
                labels: {
                    style: {
                        colors: 'rgba(255,255,255,0.8)',
                        fontSize: '10px'
                    }
                },
                axisBorder: { show: true, color: 'rgba(255,255,255,0.2)' },
                axisTicks: { show: true, color: 'rgba(255,255,255,0.2)' }
            },
            grid: { 
                show: true, 
                borderColor: 'rgba(255,255,255,0.1)',
                strokeDashArray: 4,
                padding: { left: 10, right: 10, top: 0, bottom: 0 } 
            },
            dataLabels: { enabled: false },
            tooltip: { theme: 'dark' }
        };

        // Graphique 1 : Affluence
        if (affluenceContainer) {
            const affluenceOptions = {
                ...commonOptions,
                series: [{ name: 'Billets total', data: tickets }],
                colors: ['#F5D804'],
                fill: {
                    type: 'gradient',
                    gradient: {
                        shadeIntensity: 1,
                        opacityFrom: 0.45,
                        opacityTo:   0.05,
                        stops: [0, 100],
                    }
                },
                markers: { size: 5, colors: ['#F5D804'], strokeColors: '#fff', strokeWidth: 2 },
                tooltip: {
                    ...commonOptions.tooltip,
                    y: { formatter: val => val + ' billets' },
                },
            };

            if (affluenceSkeleton) affluenceSkeleton.classList.add('hidden');
            affluenceContainer.classList.remove('hidden');
            new ApexCharts(affluenceContainer, affluenceOptions).render();
        }

        // Graphique 2 : Recettes
        if (revenueContainer) {
            const revenueOptions = {
                ...commonOptions,
                series: [{ name: 'Recette', data: revenues }],
                colors: ['#22c55e'], // Vert pour l'argent
                fill: {
                    type: 'gradient',
                    gradient: {
                        shadeIntensity: 1,
                        opacityFrom: 0.45,
                        opacityTo:   0.05,
                        stops: [0, 100],
                    }
                },
                markers: { size: 5, colors: ['#22c55e'], strokeColors: '#fff', strokeWidth: 2 },
                tooltip: {
                    ...commonOptions.tooltip,
                    y: { formatter: val => new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(val) },
                },
            };

            if (revenueSkeleton) revenueSkeleton.classList.add('hidden');
            revenueContainer.classList.remove('hidden');
            new ApexCharts(revenueContainer, revenueOptions).render();
        }

    } catch (err) {
        console.error('[stats-charts]', err);
        [affluenceSkeleton, revenueSkeleton].forEach(s => s && s.classList.add('hidden'));
        [affluenceContainer, revenueContainer].forEach(c => {
            if (c) {
                c.classList.remove('hidden');
                c.innerHTML = '<p class="text-white/40 text-sm text-center mt-8">Aucune donnée disponible</p>';
            }
        });
    }
}

if (typeof ApexCharts !== 'undefined') {
    renderAffluenceCharts();
} else {
    window.addEventListener('load', renderAffluenceCharts);
}