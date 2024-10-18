BigBrother.VisitsLineGraph = function(el) {
    let canvas, ctx, chart;

    let buildDom = function() {
        canvas = document.createElement('canvas');
        canvas.setAttribute('width', el.clientWidth);
        canvas.setAttribute('height', el.clientHeight);
        el.appendChild(canvas);

        ctx = canvas.getContext('2d');
    }

    let buildChart = function() {
        chart = new Chart(ctx, {
            type: 'line',
            data: {
                datasets: [{
                    label: _('bigbrother.daily_page_views'),
                    data: [],
                    fill: false,
                    borderColor: '#234368',
                    borderWidth: 2,
                    backgroundColor: 'rgba(35,67,104,0.1)',
                    pointBackgroundColor: '#234368',
                    pointRadius: 2,
                    pointHoverRadius: 6,
                    pointHitRadius: 6,
                    tension: 0.1,
                    xAxisID: 'x',
                },{
                    label: _('bigbrother.four_weeks_before'),
                    data: [],
                    fill: 'origin',
                    borderColor: 'rgba(131,168,241, 0.5)',
                    borderWidth: 1,
                    backgroundColor: 'rgba(131,168,241, 0.3)',
                    pointBackgroundColor: 'rgba(131,168,241, 1)',
                    pointRadius: 2,
                    pointHoverRadius: 6,
                    pointHitRadius: 6,
                    tension: 0.1,
                    xAxisID: 'xPrev',
                }]
            },
            options: {
                maintainAspectRatio: false, // Doesn't work with dynamic resizing in MODX3, so best keep the height fixed
                interaction: {
                    mode: 'x'
                },
                scales: {
                    y: {
                        grid: {
                            drawBorder: false,
                        }
                    },
                    x: {
                        type: 'time',
                        time: {
                            unit: 'day',
                            displayFormats: {
                                day: BigBrother.dateFormat()
                            },
                        },
                        min: luxon.DateTime.now().minus({days: 28}).toISODate(),
                        max: luxon.DateTime.now().toISODate(),
                        beginAtZero: true,
                        ticks: {
                            maxRotation: 0,
                            // Use a callback to set the locale - Luxon uses two characters just like cultureKey
                            callback: function(value) {
                                return luxon
                                    .DateTime
                                    .fromFormat(value, BigBrother.dateFormat())
                                    .setLocale(BigBrother.locale())
                                    .toFormat(BigBrother.dateFormat());
                            }
                        },
                        grid: {
                            drawBorder: false,
                            drawOnChartArea: false,
                            // drawTicks: false
                        }
                    },
                    xPrev: {
                        type: 'time',
                        unit: 'week',
                        min: luxon.DateTime.now().minus({days: 56}).toISODate(),
                        max: luxon.DateTime.now().minus({days: 28}).toISODate(),
                        display: false
                    }
                },
                plugins: {
                    filler: {},
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            title: function(tooltipItems) {
                                let date = tooltipItems[0].parsed.x;
                                return luxon
                                    .DateTime
                                    .fromMillis(date)
                                    .setLocale(BigBrother.locale())
                                    .toFormat(BigBrother.dateFormat());
                            },
                            // label: function(ctx) { // While this adds dates to the tooltip, it also somehow breaks the hover effect on points
                            //     return ctx.label + ': ' + ctx.parsed.y;
                            // }
                        }
                    }
                }
            }
        });
    }

    let setData = function(data) {
        if (!chart) {
            buildChart();
        }

        if (data.data) {
            chart.data.datasets.forEach((dataset, index) => {
                dataset.data = data.data[index].data;
                dataset.labels = data.data[index].labels;
            });
        }
        if (data.labels) {
            chart.data.labels = data.labels;
        }
        chart.update();
    };

    buildDom();
    return {
        key: 'visits/line',
        el: el,
        setData: setData
    }
}