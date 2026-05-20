let bolChartInstance = null;

jQuery(document).ready(function($) {

    // Default selector values
    $('#chart-metric-selector').val('commission');
    $('#chart-period-selector').val('this_year');
    $('#chart-granularity-selector').val('month'); // Changed from 'auto' to 'month'
    $('#chart-site-selector').val('all_sites');    // Added for site default

    // Helper function for Y-axis title
    function getYAxisTitle(metric) {
        switch (metric) {
            case 'commission': return 'Commission Amount (€)';
            case 'orders': return 'Number of Orders';
            case 'clicks': return 'Number of Clicks';
            case 'revenue': return 'Revenue (€)';
            case 'conversion': return 'Conversion Rate (%)';
            default: return 'Value';
        }
    }

    // Helper function for X-axis title
    // Note: Backend now determines effective_granularity, which is more reliable.
    // If PHP could pass back the `effective_granularity` in the response.data, that would be best.
    // For now, this function will use the selected granularity and period.
    function getXAxisTitle(granularity) {
        switch (granularity) {
            case 'month': return 'Month';
            case 'week': return 'Week';
            case 'day': return 'Date';
            default: return 'Period';
        }
    }


    $('#bol-test-connection-button').on('click', function() {
        var resultsDiv = $('#bol-test-connection-results');
        resultsDiv
            .removeClass('bol-status-success bol-status-error')
            .html('<span class="spinner is-active" style="float:none;margin:0 6px 0 0;"></span>Testing connection...');
        $('#bol-test-connection-button').prop('disabled', true);

        $.ajax({
            url: ajaxurl, // WordPress global variable for AJAX URL
            type: 'POST',
            data: {
                action: 'bol_test_connection', // Matches the add_action hook
                nonce: bol_settings_params.nonce // Nonce for security (will be localized)
            },
            success: function(response) {
                resultsDiv.removeClass('bol-status-success bol-status-error');
                if (response.success) {
                    resultsDiv
                        .addClass('bol-status-success')
                        .text(response.data.message);
                } else {
                    resultsDiv
                        .addClass('bol-status-error')
                        .text(response.data.message);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                resultsDiv
                    .removeClass('bol-status-success')
                    .addClass('bol-status-error')
                    .text('AJAX Error: ' + textStatus + ' - ' + errorThrown);
            },
            complete: function() {
                $('#bol-test-connection-button').prop('disabled', false);
            }
        });
    });

    $('#bol-update-chart-button').on('click', function() {
        var selectedMetric = $('#chart-metric-selector').val();
        var selectedPeriod = $('#chart-period-selector').val();
        var selectedGranularity = $('#chart-granularity-selector').val();
        var selectedSite = $('#chart-site-selector').val();
        var resultsDiv = $('#bol-chart-error-message');
        resultsDiv
            .removeClass('bol-status-success bol-status-error')
            .html('');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'bol_fetch_chart_data', 
                nonce: bol_settings_params.chart_nonce, 
                metric: selectedMetric,
                period: selectedPeriod,
                granularity: selectedGranularity,
                site: selectedSite
            },
            success: function(response) {
                if (response.success) {
                    const chartData = response.data;
                    if (bolChartInstance) {
                        bolChartInstance.destroy();
                    }
                    bolChartInstance = new Chart(document.getElementById('bolPerformanceChart').getContext('2d'), {
                        type: 'bar', // Chart type is always 'bar'
                        data: {
                            labels: chartData.labels,
                            datasets: chartData.datasets
                        },
                        options: {
                            scales: {
                                y: { 
                                    beginAtZero: true,
                                    title: {
                                        display: true,
                                        text: getYAxisTitle(selectedMetric)
                                    }
                                },
                                x: {
                                    title: {
                                        display: true,
                                        text: getXAxisTitle(selectedGranularity, selectedPeriod, chartData.labels)
                                    }
                                }
                            },
                            responsive: true,
                            maintainAspectRatio: true
                        }
                    });

                    // Add this line, passing selectedMetric as well for context in table formatting
                    populateChartDataTable(chartData, selectedMetric);

                    // Handle notices from backend (e.g. conversion calculation note)
                    if (chartData.notice) {
                        resultsDiv
                            .addClass('bol-status-success')
                            .text(chartData.notice);
                    }

                    if (chartData.generated_at) {
                        $('#bol-chart-last-updated').text('Last updated: ' + chartData.generated_at);
                    }
                } else {
                    resultsDiv
                        .addClass('bol-status-error')
                        .text('Error loading chart data: ' + response.data.message);
                    // Clear the table if there's an error
                    populateChartDataTable(null, selectedMetric); // Pass null to clear
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                resultsDiv
                    .addClass('bol-status-error')
                    .text('AJAX Error: ' + textStatus + ' - ' + errorThrown);
            },
            beforeSend: function() {
                // Show a loading indicator
                if (bolChartInstance) { // Dim current chart while loading
                    bolChartInstance.ctx.canvas.style.opacity = 0.5;
                }
                $('#bol-update-chart-button')
                    .prop('disabled', true)
                    .text('Loading…');
                $('#bol-chart-loading-indicator')
                    .addClass('is-active')
                    .show();
            },
            complete: function() {
                // Hide loading indicator
                if (bolChartInstance) {
                    bolChartInstance.ctx.canvas.style.opacity = 1;
                }
                $('#bol-update-chart-button')
                    .prop('disabled', false)
                    .text('Update Chart');
                $('#bol-chart-loading-indicator')
                    .removeClass('is-active')
                    .hide();
            }
        });
    }); 

    // Trigger initial chart load with defaults
    $('#bol-update-chart-button').trigger('click');

    // Function to populate the data table under the chart
    function populateChartDataTable(chartData, metric) {
        var tableContainer = $('#bol-chart-data-table-container');
        tableContainer.html(''); // Clear previous content

        if (!chartData || !chartData.labels || chartData.labels.length === 0 || 
            !chartData.datasets || chartData.datasets.length === 0 || 
            !chartData.datasets[0].data || chartData.datasets[0].data.length === 0) {
            tableContainer.html('<p>No data to display in table.</p>');
            return;
        }

        var table = $('<table>').addClass('wp-list-table widefat striped');
        var thead = $('<thead>').appendTo(table);
        var tbody = $('<tbody>').appendTo(table);
        var headerRow = $('<tr>').appendTo(thead);

        // Determine header names
        var xLabel = 'Period'; // Default X-axis label for table
        var selectedGranularity = $('#chart-granularity-selector').val();
        var selectedPeriod = $('#chart-period-selector').val();

        // Simplified version of getXAxisTitle for table header:
        if (selectedGranularity === 'month' || (selectedGranularity === 'auto' && (selectedPeriod === 'this_year' || selectedPeriod === 'last_year'))) {
            xLabel = 'Month';
        } else if (selectedGranularity === 'week' || (selectedGranularity === 'auto' && selectedPeriod === 'last_4_weeks')) {
            xLabel = 'Week';
        } else if (selectedGranularity === 'day') {
            xLabel = 'Date';
        }
        // Fallback to selectedGranularity if it's not 'auto' and not caught above.
        else if (selectedGranularity !== 'auto') {
            xLabel = selectedGranularity.charAt(0).toUpperCase() + selectedGranularity.slice(1);
        }


        $('<th>').text(xLabel).appendTo(headerRow);
        $('<th>').text(chartData.datasets[0].label || getYAxisTitle(metric) || 'Value').appendTo(headerRow);

        // Populate table rows
        for (var i = 0; i < chartData.labels.length; i++) {
            var dataRow = $('<tr>').appendTo(tbody);
            $('<td>').text(chartData.labels[i]).appendTo(dataRow);
            
            var val = chartData.datasets[0].data[i];
            if (typeof val === 'number') {
                if (metric === 'commission' || metric === 'revenue') {
                    val = '€' + val.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                } else if (metric === 'conversion') {
                    val = val.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '%';
                } else { // For orders, clicks
                    val = val.toLocaleString();
                }
            }
            $('<td>').text(val).appendTo(dataRow);
        }

        tableContainer.append(table);
    }

    $('#bol-clear-cache-button').on('click', function() {
        var resultSpan = $('#bol-clear-cache-result');
        var button = $(this);

        button.prop('disabled', true).text('Bezig…');
        resultSpan.removeClass('bol-status-success bol-status-error').text('');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'bol_clear_cache',
                nonce: bol_settings_params.clear_cache_nonce
            },
            success: function(response) {
                if (response.success) {
                    resultSpan.addClass('bol-status-success').text(response.data.message);
                } else {
                    resultSpan.addClass('bol-status-error').text(response.data.message);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                resultSpan.addClass('bol-status-error').text('AJAX fout: ' + textStatus);
            },
            complete: function() {
                button.prop('disabled', false).text('Cache legen');
            }
        });
    });

    // Initialize datepickers
    $('.datepicker').each(function(){
        $(this).datepicker({
            dateFormat: 'yy-mm-dd', // ISO 8601 format
            changeMonth: true,
            changeYear: true
        });
    });
});
