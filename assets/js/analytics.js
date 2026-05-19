/* Analytics annual chart. */
(function () {
    const a = window.__analytics;
    if (!a) return;
    const ctx = document.getElementById('annualChart');
    if (!ctx) return;

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: a.series.labels,
            datasets: [
                { label: 'Income',  data: a.series.income,  backgroundColor: '#198754' },
                { label: 'Expense', data: a.series.expense, backgroundColor: '#dc3545' },
            ],
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'bottom' } },
            scales: { y: { beginAtZero: true } },
        },
    });
})();
