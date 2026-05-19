/* Reports page charts. */
(function () {
    const r = window.__report;
    if (!r) return;

    const trendCtx = document.getElementById('reportChart');
    if (trendCtx) {
        new Chart(trendCtx, {
            type: r.view === 'yearly' ? 'bar' : 'line',
            data: {
                labels: r.series.labels,
                datasets: [
                    { label: 'Income',  data: r.series.income,  borderColor: '#198754',
                      backgroundColor: 'rgba(25,135,84,0.4)', tension: .35 },
                    { label: 'Expense', data: r.series.expense, borderColor: '#dc3545',
                      backgroundColor: 'rgba(220,53,69,0.4)', tension: .35 },
                ],
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'bottom' } },
                scales: { y: { beginAtZero: true } },
            },
        });
    }

    const catCtx = document.getElementById('reportCatChart');
    if (catCtx && r.byCat.length) {
        new Chart(catCtx, {
            type: 'pie',
            data: {
                labels: r.byCat.map((c) => c.category),
                datasets: [{
                    data: r.byCat.map((c) => parseFloat(c.total)),
                    backgroundColor: [
                        '#0d6efd', '#198754', '#dc3545', '#ffc107', '#6f42c1',
                        '#20c997', '#fd7e14', '#0dcaf0', '#6610f2', '#d63384',
                    ],
                }],
            },
            options: { plugins: { legend: { position: 'bottom' } } },
        });
    }
})();
