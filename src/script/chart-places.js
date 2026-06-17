document.addEventListener("DOMContentLoaded", function () {
  const chart = new ApexCharts(document.querySelector("#bar-places"), {
    series: [{ data: [3128, 2420] }],
    chart: {
      type: "bar",
      width: "100%",  // S'adapte à la largeur du div Tailwind
      height: "100%", // S'adapte à la hauteur du div Tailwind
      toolbar: { show: false }
    },
    plotOptions: {
      bar: {
        columnWidth: "55%",
        borderRadius: 6,
        distributed: true,
        dataLabels: {
          position: "top" 
        }
      }
    },
    colors: ["#15277B", "#C8D0DC"],
    dataLabels: {
      enabled: true,
      formatter: val => val.toLocaleString(),
      offsetY: -22,
      style: { 
        fontSize: "11px", 
        fontWeight: "700", 
        colors: ["#15277B", "#888888"] 
      }
    },
    xaxis: {
      categories: ["ce mois ci", "mois dernier"],
      labels: { style: { fontSize: "10px", colors: ["#15277B", "#888888"] } },
      axisBorder: { show: false },
      axisTicks: { show: false }
    },
    yaxis: { show: false },
    grid: { 
      show: false,
      padding: { top: 20 }
    },
    legend: { show: false },
    tooltip: { enabled: false }
  });
 
  chart.render();
});