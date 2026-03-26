document.addEventListener('DOMContentLoaded', function () {
    function showErrorBox(errorBox, message) {
        if (!errorBox) return;
        errorBox.style.display = 'block';
        errorBox.textContent = message;
    }

    function buildDataset(label, data, chartType) {
        return {
            label: label,
            data: Array.isArray(data) ? data : [],
            borderWidth: 2,
            borderRadius: chartType === 'bar' ? 8 : 0,
            tension: chartType === 'line' ? 0.35 : 0,
            fill: false,
            pointRadius: chartType === 'line' ? 4 : 0,
            pointHoverRadius: chartType === 'line' ? 6 : 0
        };
    }

    function renderSingleSeriesChart(canvasId, errorBoxId, chartData) {
        const canvas = document.getElementById(canvasId);
        const errorBox = document.getElementById(errorBoxId);

        if (!canvas) return;
        if (!chartData) {
            showErrorBox(errorBox, "Aucune donnée de graphique n’a été transmise à la page.");
            return;
        }
        if (typeof Chart === 'undefined') {
            showErrorBox(errorBox, "Chart.js n’a pas pu être chargé.");
            return;
        }

        try {
            const labels = Array.isArray(chartData.labels) ? chartData.labels : [];
            const values = Array.isArray(chartData.values) ? chartData.values : [];
            const chartType = ['bar', 'line'].includes(chartData.chartType) ? chartData.chartType : 'bar';

            if (labels.length === 0 || values.length === 0) {
                showErrorBox(errorBox, "Le graphique ne peut pas s’afficher : aucune donnée exploitable.");
                return;
            }

            const ctx = canvas.getContext('2d');

            new Chart(ctx, {
                type: chartType,
                data: {
                    labels: labels,
                    datasets: [
                        buildDataset(chartData.yAxisLabel || 'Valeur', values, chartType)
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    plugins: {
                        legend: {
                            display: true
                        },
                        tooltip: {
                            enabled: true
                        }
                    },
                    scales: {
                        x: {
                            title: {
                                display: true,
                                text: chartData.xAxisLabel || 'Axe X'
                            }
                        },
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: chartData.yAxisLabel || 'Axe Y'
                            }
                        }
                    }
                }
            });
        } catch (error) {
            console.error('Erreur graphique simple :', error);
            showErrorBox(errorBox, "Erreur lors du rendu du graphique : " + error.message);
        }
    }

    function renderMultiSeriesChart(canvasId, errorBoxId, chartData) {
        const canvas = document.getElementById(canvasId);
        const errorBox = document.getElementById(errorBoxId);

        if (!canvas) return;
        if (!chartData) {
            showErrorBox(errorBox, "Aucune donnée analytique n’a été transmise à la page.");
            return;
        }
        if (typeof Chart === 'undefined') {
            showErrorBox(errorBox, "Chart.js n’a pas pu être chargé.");
            return;
        }

        try {
            const labels = Array.isArray(chartData.labels) ? chartData.labels : [];
            const creditValues = Array.isArray(chartData.creditValues) ? chartData.creditValues : [];
            const debitValues = Array.isArray(chartData.debitValues) ? chartData.debitValues : [];
            const netValues = Array.isArray(chartData.netValues) ? chartData.netValues : [];
            const chartType = ['bar', 'line'].includes(chartData.chartType) ? chartData.chartType : 'line';

            if (labels.length === 0 || (creditValues.length === 0 && debitValues.length === 0 && netValues.length === 0)) {
                showErrorBox(errorBox, "Le graphique analytique ne peut pas s’afficher : aucune donnée exploitable.");
                return;
            }

            const ctx = canvas.getContext('2d');

            new Chart(ctx, {
                type: chartType,
                data: {
                    labels: labels,
                    datasets: [
                        buildDataset('Net', netValues, chartType),
                        buildDataset('Crédits', creditValues, chartType),
                        buildDataset('Débits', debitValues, chartType)
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    plugins: {
                        legend: {
                            display: true
                        },
                        tooltip: {
                            enabled: true
                        }
                    },
                    scales: {
                        x: {
                            title: {
                                display: true,
                                text: chartData.xAxisLabel || 'Axe X'
                            }
                        },
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Montants'
                            }
                        }
                    }
                }
            });
        } catch (error) {
            console.error('Erreur graphique analytique :', error);
            showErrorBox(errorBox, "Erreur lors du rendu analytique : " + error.message);
        }
    }

    renderSingleSeriesChart(
        'operationsChart',
        'chartErrorBox',
        typeof window.dashboardChartData !== 'undefined' ? window.dashboardChartData : null
    );

    renderMultiSeriesChart(
        'analyticsChart',
        'analyticsChartErrorBox',
        typeof window.analyticsChartData !== 'undefined' ? window.analyticsChartData : null
    );
});