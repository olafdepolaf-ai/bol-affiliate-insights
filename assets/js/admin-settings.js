let bolChartInstance = null;

jQuery(document).ready(function($) {
    $('#bol-test-connection-button').on('click', function() {
        var resultsDiv = $('#bol-test-connection-results');
        resultsDiv.html('<p>Testing connection...</p>');

        $.ajax({
            url: ajaxurl, // WordPress global variable for AJAX URL
            type: 'POST',
            data: {
                action: 'bol_test_connection', // Matches the add_action hook
                nonce: bol_settings_params.nonce // Nonce for security (will be localized)
            },
            success: function(response) {
                if (response.success) {
                    resultsDiv.html('<p style="color: green;">' + response.data.message + '</p>');
                } else {
                    resultsDiv.html('<p style="color: red;">' + response.data.message + '</p>');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                resultsDiv.html('<p style="color: red;">AJAX Error: ' + textStatus + ' - ' + errorThrown + '</p>');
            }
        });
    });

    $('#bol-update-chart-button').on('click', function() {
        var selectedMetric = $('#chart-metric-selector').val();
        var selectedPeriod = $('#chart-period-selector').val();
        var selectedSite = $('#chart-site-selector').val(); // Will be used later
        var resultsDiv = $('#bol-chart-error-message'); // For errors
        resultsDiv.html(''); // Clear previous errors

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'bol_fetch_chart_data', // New action
                nonce: bol_settings_params.chart_nonce, // New nonce for this action
                metric: selectedMetric,
                period: selectedPeriod,
                site: selectedSite
            },
            success: function(response) {
                if (response.success) {
                    const chartData = response.data;
                    if (bolChartInstance) {
                        bolChartInstance.destroy();
                    }
                    bolChartInstance = new Chart(document.getElementById('bolPerformanceChart').getContext('2d'), {
                        type: 'bar', // Or line, based on metric/preference
                        data: {
                            labels: chartData.labels,
                            datasets: chartData.datasets
                        },
                        options: {
                            scales: {
                                y: { beginAtZero: true }
                            },
                            responsive: true,
                            maintainAspectRatio: true
                        }
                    });
                } else {
                    resultsDiv.html('<p>Error loading chart data: ' + response.data.message + '</p>');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                resultsDiv.html('<p>AJAX Error: ' + textStatus + ' - ' + errorThrown + '</p>');
            },
            beforeSend: function() {
                // Optional: Show a loading indicator
                if (bolChartInstance) { // Dim current chart while loading
                    bolChartInstance.ctx.canvas.style.opacity = 0.5;
                }
                 $('#bol-update-chart-button').prop('disabled', true).text('Loading...');
            },
            complete: function() {
                // Optional: Hide loading indicator
                if (bolChartInstance) {
                     bolChartInstance.ctx.canvas.style.opacity = 1;
                }
                $('#bol-update-chart-button').prop('disabled', false).text('Update Chart');
            }
        });
    }); // Removed .trigger('click')

    // Initialize datepickers
    $('.datepicker').each(function(){
        $(this).datepicker({
            dateFormat: 'yy-mm-dd', // ISO 8601 format
            changeMonth: true,
            changeYear: true
        });
    });
});
