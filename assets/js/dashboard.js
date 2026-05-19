/* Dashboard charts (Chart.js). Reads window.__dashboard.
 * Provides a Week / Month / Year range switcher for both charts.
 */
(function () {
    function init() {
        const data = window.__dashboard;
        if (!data) return;
        if (typeof Chart === 'undefined') { setTimeout(init, 50); return; }

        // ----- Palette (matches "Sunshine" / iOS bubble feel) -----
        const C = {
            coral:  '#FF6B9D',
            rose:   '#EF476F',
            peach:  '#FFA07A',
            orange: '#FF8C42',
            yellow: '#FFC857',
            mint:   '#06D6A0',
            sky:    '#4CC9F0',
            blue:   '#5B8DEF',
            lav:    '#C8B6FF',
            indigo: '#7B61FF',
        };
        const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
        const ink    = isDark ? '#F8FAFC' : '#1F2937';
        const muted  = isDark ? '#B4BCC9' : '#5B6471';
        const grid   = isDark ? 'rgba(255,255,255,.08)' : 'rgba(15,23,42,.08)';
        const curr   = data.currency || 'RM';

        Chart.defaults.font.family =
            "-apple-system,BlinkMacSystemFont,'SF Pro Display','Inter','Segoe UI',Roboto,sans-serif";
        Chart.defaults.color = muted;

        function shortMoney(v) {
            const n = Number(v) || 0;
            const abs = Math.abs(n);
            if (abs >= 1000) return curr + ' ' + (n / 1000).toFixed(n % 1000 === 0 ? 0 : 1) + 'k';
            return curr + ' ' + n.toLocaleString(undefined, { maximumFractionDigits: 0 });
        }
        function money(v) {
            return curr + ' ' + (Number(v) || 0).toLocaleString(undefined, {
                minimumFractionDigits: 2, maximumFractionDigits: 2,
            });
        }
        function formatLabel(label, range) {
            // Month / Week → daily labels are "YYYY-MM-DD"; show "Apr 16".
            // Year → labels are already "Jan", "Feb", … (from monthlySeries).
            if (range === 'year') return label;
            const t = new Date(label);
            if (isNaN(t)) return label;
            return t.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
        }

        // ===================================================
        //   Spending chart — Week / Month / Year
        // ===================================================
        const trendEl = document.getElementById('spendChart');
        const titleEl = document.getElementById('spendTitle');
        let trendChart = null;

        function buildTrend(range) {
            if (!trendEl) return;
            const s = (data.ranges && data.ranges.series && data.ranges.series[range]) || data.series;
            if (!s || !Array.isArray(s.labels)) return;

            const labels  = s.labels.map((l) => formatLabel(l, range));
            const income  = s.income.map(Number);
            const expense = s.expense.map(Number);

            // Smart moving-average window: 3 for week, 7 for month, 3 for year.
            const win = range === 'month' ? 7 : 3;
            const avg = expense.map((_, i) => {
                const w = expense.slice(Math.max(0, i - (win - 1)), i + 1);
                const sum = w.reduce((a, b) => a + b, 0);
                return +(sum / w.length).toFixed(2);
            });

            if (trendChart) trendChart.destroy();
            trendEl.dataset.rendered = '1';

            trendChart = new Chart(trendEl, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        { label: 'Expense', data: expense, backgroundColor: C.coral,
                          borderRadius: 6, borderSkipped: false, maxBarThickness: 22, order: 2 },
                        { label: 'Income',  data: income,  backgroundColor: C.mint,
                          borderRadius: 6, borderSkipped: false, maxBarThickness: 22, order: 2 },
                        { label: win + '-' + (range === 'year' ? 'mo' : 'd') + ' avg',
                          data: avg, type: 'line',
                          borderColor: C.indigo, backgroundColor: C.indigo,
                          borderWidth: 2.5, pointRadius: 0, pointHoverRadius: 4,
                          tension: 0.35, order: 1 },
                    ],
                },
                options: {
                    responsive: true,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { position: 'bottom',
                            labels: { usePointStyle: true, boxWidth: 8, padding: 16, color: ink } },
                        tooltip: {
                            backgroundColor: isDark ? 'rgba(20,21,27,.95)' : 'rgba(255,255,255,.98)',
                            titleColor: ink, bodyColor: ink,
                            borderColor: grid, borderWidth: 1,
                            padding: 12, cornerRadius: 12, usePointStyle: true,
                            callbacks: { label: (ctx) => ctx.dataset.label + ': ' + money(ctx.parsed.y) },
                        },
                    },
                    scales: {
                        x: { grid: { display: false },
                             ticks: { color: muted, autoSkip: true, maxRotation: 0, autoSkipPadding: 12 } },
                        y: { beginAtZero: true,
                             grid: { color: grid, drawBorder: false },
                             ticks: { color: muted, callback: (v) => shortMoney(v) } },
                    },
                },
            });

            if (titleEl) {
                titleEl.textContent =
                    range === 'week'  ? 'Spending — last 7 days' :
                    range === 'year'  ? 'Spending — this year (monthly)' :
                                        'Spending — last 30 days';
            }
        }

        // ===================================================
        //   By category — Week / Month / Year
        // ===================================================
        const catEl    = document.getElementById('catChart');
        const catTitle = document.getElementById('catTitle');
        const catEmpty = document.getElementById('catEmpty');
        let catChart = null;

        // Plugin: draw centre label
        const centreLabel = {
            id: 'centreLabel',
            afterDraw(chart) {
                const { ctx, chartArea: ca, $cl: cl } = chart;
                if (!ca || !cl) return;
                const cx = (ca.left + ca.right) / 2;
                const cy = (ca.top + ca.bottom) / 2;
                ctx.save();
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillStyle = muted;
                ctx.font = '600 11px ' + Chart.defaults.font.family;
                ctx.fillText('Total spent', cx, cy - 16);
                ctx.fillStyle = ink;
                ctx.font = '800 18px ' + Chart.defaults.font.family;
                ctx.fillText(cl.totalLabel, cx, cy + 4);
                if (cl.topLabel) {
                    ctx.fillStyle = muted;
                    ctx.font = '600 11px ' + Chart.defaults.font.family;
                    ctx.fillText(cl.topLabel, cx, cy + 22);
                }
                ctx.restore();
            },
        };

        function buildCat(range) {
            if (!catEl) return;
            const raw = (data.ranges && data.ranges.byCat && data.ranges.byCat[range]) || data.byCat || [];
            const palette = [C.coral, C.orange, C.yellow, C.mint, C.sky, C.lav];

            if (catTitle) {
                const label = (data.ranges && data.ranges.labels && data.ranges.labels[range]) || 'this month';
                catTitle.textContent = 'By category — ' + label;
            }

            if (catChart) { catChart.destroy(); catChart = null; }

            if (!raw.length) {
                catEl.classList.add('d-none');
                if (catEmpty) catEmpty.classList.remove('d-none');
                return;
            }
            catEl.classList.remove('d-none');
            if (catEmpty) catEmpty.classList.add('d-none');

            const sorted = [...raw].sort((a, b) => parseFloat(b.total) - parseFloat(a.total));
            const top = sorted.slice(0, 5);
            const rest = sorted.slice(5);
            const restTotal = rest.reduce((s, r) => s + parseFloat(r.total), 0);
            if (restTotal > 0) top.push({ category: 'Other', total: restTotal });
            const total = top.reduce((s, r) => s + parseFloat(r.total), 0);

            catEl.dataset.rendered = '1';

            catChart = new Chart(catEl, {
                type: 'doughnut',
                data: {
                    labels: top.map((c) => c.category),
                    datasets: [{
                        data: top.map((c) => parseFloat(c.total)),
                        backgroundColor: top.map((_, i) => palette[i % palette.length]),
                        borderWidth: 0,
                        hoverOffset: 8,
                    }],
                },
                options: {
                    responsive: true,
                    cutout: '68%',
                    plugins: {
                        legend: { position: 'bottom',
                            labels: { usePointStyle: true, boxWidth: 8, padding: 12,
                                      color: ink, font: { size: 11 } } },
                        tooltip: {
                            backgroundColor: isDark ? 'rgba(20,21,27,.95)' : 'rgba(255,255,255,.98)',
                            titleColor: ink, bodyColor: ink,
                            borderColor: grid, borderWidth: 1,
                            padding: 12, cornerRadius: 12, usePointStyle: true,
                            callbacks: {
                                label: (ctx) => {
                                    const v = ctx.parsed;
                                    const pct = total ? Math.round((v / total) * 100) : 0;
                                    return ctx.label + ': ' + money(v) + ' (' + pct + '%)';
                                },
                            },
                        },
                    },
                },
                plugins: [centreLabel],
            });

            // Stash data for the centreLabel plugin via $cl
            const topPct = total ? Math.round((parseFloat(top[0].total) / total) * 100) : 0;
            catChart.$cl = {
                totalLabel: money(total),
                topLabel:   top.length ? (top[0].category + ' · ' + topPct + '%') : '',
            };
            catChart.update();
        }

        // Wire up range/category buttons using Bootstrap btn-group behavior.
        function wireSwitch(selector, attr, builder) {
            const buttons = document.querySelectorAll(selector);
            buttons.forEach((b) => {
                b.addEventListener('click', () => {
                    const group = b.closest('.btn-group') || b.parentElement;
                    if (group) {
                        // Find sibling buttons within the same group that match the selector.
                        const sibs = Array.from(group.querySelectorAll(selector));
                        sibs.forEach((s) => {
                            const isActive = s === b;
                            s.classList.toggle('active', isActive);
                            s.setAttribute('aria-pressed', isActive ? 'true' : 'false');
                            if (isActive) {
                                s.classList.remove('btn-outline-secondary');
                                s.classList.add('btn-primary');
                            } else {
                                s.classList.remove('btn-primary');
                                if (!s.classList.contains('btn-outline-secondary')) s.classList.add('btn-outline-secondary');
                            }
                        });
                    } else {
                        // Fallback: toggle active on clicked button only
                        buttons.forEach((s) => s.classList.toggle('active', s === b));
                    }
                    builder(b.getAttribute(attr));
                });
            });
        }

        wireSwitch('[data-range]', 'data-range', buildTrend);
        wireSwitch('[data-cat-range]', 'data-cat-range', buildCat);

        // Initial draw — defaults match the .active button.
        buildTrend('month');
        buildCat('month');
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
