document.addEventListener("DOMContentLoaded", function () {
  const chart = new ApexCharts(document.querySelector("#pie-invitations"), {
    series: [76.9, 23.1],
    chart: {
      type: "pie",
      width: 160,
      height: 160,
      toolbar: { show: false }
    },
    labels: ["nombre d'invitations", "Nombres de Places Vendues"],
    colors: ["#E8EEF7", "#15277B"],
    dataLabels: {
      enabled: true,
      formatter: function(val, opts) {
        return opts.w.config.labels[opts.seriesIndex] + "\n" + val.toFixed(1) + "%";
      },
      style: {
        fontSize: "9px",
        fontWeight: "400",
        colors: ["#444", "#444"]
      },
      dropShadow: { enabled: false }
    },
    legend: { show: false },
    stroke: { width: 2, colors: ["#fff"] },
    tooltip: { enabled: false }
  });
 
  chart.render();
});