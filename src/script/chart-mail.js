const chart = new ApexCharts(document.querySelector("#pie-chart"), {
  series: [67.5, 32.5],
  chart: { type: "pie", width: 280, height: 280, toolbar: { show: false } },
  labels: ["Mails envoyés", "manquantes"],
  colors: ["#15277B", "#F5D804"],
  dataLabels: {
    enabled: true,
    formatter: val => val.toFixed(1) + "%",
    style: { fontSize: "12px", fontWeight: "400", colors: ["#000000"] }
  },
  legend: { show: true, position: "bottom", fontSize: "13px" },
  stroke: { width: 0 }
});
chart.render();