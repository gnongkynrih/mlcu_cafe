import Chart from 'chart.js/auto';
window.Chart = Chart;   // make it available globally

// ₹1,234 — Indian digit grouping, no paise on the axis.
const rupees = new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 0 });

/**
 * Draws one dashboard chart with the cafe's look: matching text and grid
 * colours for light and dark mode, and ₹ on money axes and tooltips.
 *
 * Blade only passes the data. Example:
 *   dashboardChart(canvas, { type: 'bar', labels: [...], datasets: [...], money: true })
 *
 * @param {HTMLCanvasElement} canvas
 * @param {{type: string, labels: string[], datasets: object[], money?: boolean, horizontal?: boolean}} config
 * @returns {Chart}
 */
window.dashboardChart = (canvas, { type, labels, datasets, money = true, horizontal = false }) => {
    const isDark = document.documentElement.classList.contains('dark');
    const textColour = isDark ? '#a1a1aa' : '#78716c';      // zinc-400 / stone-500
    const gridColour = isDark ? 'rgba(63, 63, 70, 0.6)' : 'rgba(231, 229, 228, 0.9)';
    const format = (value) => (money ? rupees.format(value) : value);
    const isRound = type === 'doughnut' || type === 'pie';

    // The axis that holds the numbers: y normally, x when the bars lie flat.
    const valueAxis = horizontal ? 'x' : 'y';
    const labelAxis = horizontal ? 'y' : 'x';

    return new Chart(canvas, {
        type,
        data: { labels, datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: horizontal ? 'y' : 'x',
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: {
                    display: isRound,
                    position: 'bottom',
                    labels: { color: textColour, usePointStyle: true, padding: 16 },
                },
                tooltip: {
                    callbacks: {
                        label: (item) => `${item.dataset.label || item.label}: ${format(item.raw)}`,
                    },
                },
            },
            cutout: type === 'doughnut' ? '65%' : undefined,
            scales: isRound ? {} : {
                [valueAxis]: {
                    beginAtZero: true,
                    border: { display: false },
                    grid: { color: gridColour },
                    ticks: { color: textColour, precision: 0, callback: format },
                },
                [labelAxis]: {
                    border: { display: false },
                    grid: { display: false },
                    ticks: { color: textColour },
                },
            },
        },
    });
};
