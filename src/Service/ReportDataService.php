<?php
namespace TuinenBalkon\BolAffiliateInsights\Service;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class ReportDataService {
    private $api_client;

    public function __construct(ApiClient $api_client) {
        $this->api_client = $api_client;
    }

    public function get_chart_data($metric, $period, $granularity, $site_filter) {
        list($start_date, $end_date) = $this->calculate_date_range($period);
        $effective_granularity = $this->determine_effective_granularity($granularity, $start_date, $end_date);
        
        $response_data = $this->fetch_and_process_chart_data($metric, $start_date, $end_date, $effective_granularity, $site_filter);
        $response_data['effective_granularity'] = $effective_granularity;

        return $response_data;
    }

    private function calculate_date_range($period) {
        $end_date = new \DateTimeImmutable('now', wp_timezone());
        switch ($period) {
            case 'last_4_weeks':
                return [$end_date->modify('-27 days'), $end_date];
            case 'this_year':
                return [new \DateTimeImmutable(date('Y-01-01'), wp_timezone()), new \DateTimeImmutable('today', wp_timezone())];
            case 'last_year':
                $last_year = (int)date('Y') - 1;
                return [new \DateTimeImmutable($last_year . '-01-01', wp_timezone()), new \DateTimeImmutable($last_year . '-12-31', wp_timezone())];
            case 'this_month':
                return [new \DateTimeImmutable('first day of this month', wp_timezone()), new \DateTimeImmutable('today', wp_timezone())];
            case 'last_month':
                 return [new \DateTimeImmutable('first day of last month', wp_timezone()), new \DateTimeImmutable('last day of last month', wp_timezone())];
            case 'last_30_days':
                return [$end_date->modify('-29 days'), $end_date];
            case 'last_7_days':
                return [$end_date->modify('-6 days'), $end_date];
            default:
                return [$end_date->modify('-27 days'), $end_date]; // Fallback to default
        }
    }

    private function determine_effective_granularity($granularity, $start_date, $end_date) {
        if ($granularity !== 'auto') {
            return $granularity;
        }
        $diff_days = $end_date->diff($start_date)->days;
        if ($diff_days > 365) return 'month';
        if ($diff_days > 42) return 'week';
        return 'day';
    }

    private function fetch_and_process_chart_data($metric, $start_date, $end_date, $granularity, $site_filter) {
        $start_date_str = $start_date->format('Y-m-d');
        $end_date_str = $end_date->format('Y-m-d');

        error_log('ReportDataService: fetch_and_process_chart_data - start_date_str: ' . $start_date_str . ', end_date_str: ' . $end_date_str . ', metric: ' . $metric);
        if ($metric === 'commission') {
            $response = $this->api_client->get_orders_report($start_date_str, $end_date_str);
            $date_key = 'orderDateTime';
        } else {
            $response = $this->api_client->get_promotion_methods_report($start_date_str, $end_date_str);
            $date_key = 'date';
        }

        error_log('ReportDataService: API Response for ' . $metric . ': ' . print_r($response, true));

        if (is_wp_error($response)) {
            throw new \Exception('Error fetching API data: ' . $response->get_error_message());
        }
        if (!isset($response['items'])) {
            throw new \Exception('API response is not in the expected format.');
        }

        $items = $response['items'];
        if ($site_filter !== 'all_sites' && $metric !== 'commission') {
            $items = array_filter($items, function ($item) use ($site_filter) {
                return isset($item['siteCode']) && $item['siteCode'] == $site_filter;
            });
        }
        error_log('ReportDataService: Items after site filter: ' . print_r($items, true));

        return $this->aggregate_chart_data($items, $metric, $granularity, $start_date, $end_date, $date_key);
    }

    private function initialize_aggregated_data($granularity, $start_date, $end_date) {
        $aggregated_data = [];
        $current_date = new \DateTimeImmutable($start_date->format('Y-m-d'), wp_timezone());
        $end_date_loop = new \DateTimeImmutable($end_date->format('Y-m-d'), wp_timezone());

        while ($current_date <= $end_date_loop) {
            $key = '';
            $label = '';
            switch ($granularity) {
                case 'day':
                    $key = $current_date->format('Y-m-d');
                    $label = $current_date->format('d M');
                    $current_date = $current_date->add(new \DateInterval('P1D'));
                    break;
                case 'week':
                    $key = $current_date->format('Y-W');
                    $label = 'Week ' . $current_date->format('W');
                    $current_date = $current_date->add(new \DateInterval('P1W'));
                    break;
                case 'month':
                    $key = $current_date->format('Y-m');
                    $label = $current_date->format('M Y');
                    $current_date = $current_date->add(new \DateInterval('P1M'));
                    break;
            }
            $aggregated_data[$key] = ['label' => $label, 'value' => 0, 'clicks' => 0, 'orders' => 0];
        }
        return $aggregated_data;
    }

    private function aggregate_chart_data($items, $metric, $granularity, $start_date, $end_date, $date_key) {
        $aggregated_data = $this->initialize_aggregated_data($granularity, $start_date, $end_date);
        error_log('ReportDataService: aggregate_chart_data - Initial aggregated_data: ' . print_r($aggregated_data, true));

        foreach ($items as $item) {
            $item_date = new \DateTimeImmutable($item[$date_key], wp_timezone());

            // Only process data within the requested date range
            if ($item_date < $start_date || $item_date > $end_date) {
                error_log('ReportDataService: Skipping item outside date range: ' . $item_date->format('Y-m-d H:i:s'));
                continue;
            }

            $key = '';
            switch ($granularity) {
                case 'day':
                    $key = $item_date->format('Y-m-d');
                    break;
                case 'week':
                    $key = $item_date->format('Y-W');
                    break;
                case 'month':
                    $key = $item_date->format('Y-m');
                    break;
            }

            if (!isset($aggregated_data[$key])) {
                // This should ideally not happen if aggregated_data is pre-filled correctly
                // but as a safeguard, initialize if a key is missing
                error_log('ReportDataService: Key ' . $key . ' not found in aggregated_data. Initializing.');
                $aggregated_data[$key] = ['label' => '', 'value' => 0, 'clicks' => 0, 'orders' => 0];
            }

            switch ($metric) {
                case 'commission':
                    if (isset($item['commission'])) {
                        $commission_value = $item['commission'];
                        if (is_string($commission_value)) {
                            $commission_value = str_replace(',', '.', $commission_value);
                        }
                        $aggregated_data[$key]['value'] += (float) $commission_value;
                    }
                    break;
                case 'orders':
                    $aggregated_data[$key]['value'] += 1;
                    break;
                case 'clicks':
                    if (isset($item['clicks'])) {
                        $aggregated_data[$key]['value'] += (int) $item['clicks'];
                    }
                    break;
                case 'revenue':
                    if (isset($item['revenueOriginalInclVat'])) {
                        $aggregated_data[$key]['value'] += (float) $item['revenueOriginalInclVat'];
                    }
                    break;
                case 'conversion':
                    if (isset($item['clicks'])) {
                        $aggregated_data[$key]['clicks'] += (int) $item['clicks'];
                    }
                    if ($date_key === 'orderDateTime') {
                        $aggregated_data[$key]['orders'] += 1;
                    }
                    break;
            }
            error_log('ReportDataService: Aggregated data for key ' . $key . ': ' . print_r($aggregated_data[$key], true));
        }

        $labels = [];
        $data = [];
        foreach ($aggregated_data as $entry) {
            $labels[] = $entry['label'];
            if ($metric === 'conversion') {
                $conversion_rate = ($entry['clicks'] > 0) ? ($entry['orders'] / $entry['clicks']) * 100 : 0;
                $data[] = round($conversion_rate, 2);
            } else {
                $data[] = $entry['value'];
            }
        }
        error_log('ReportDataService: Final labels: ' . print_r($labels, true));
        error_log('ReportDataService: Final data: ' . print_r($data, true));

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => ucfirst($metric),
                    'data' => $data,
                    'backgroundColor' => 'rgba(0, 115, 170, 0.5)',
                    'borderColor' => 'rgba(0, 115, 170, 1)',
                    'borderWidth' => 1,
                ]
            ]
        ];
    }

    public function get_saldo_metrics() {
        $end_date = new \DateTimeImmutable('now', wp_timezone());
        $start_date = $end_date->modify('-89 days');
        $start_date_str = $start_date->format('Y-m-d');
        $end_date_str = $end_date->format('Y-m-d');

        $response = $this->api_client->get_orders_report($start_date_str, $end_date_str);

        if (is_wp_error($response) || !isset($response['items'])) {
            return [
                'approved' => 0,
                'pending' => 0,
                'total' => 0,
            ];
        }

        $items = $response['items'];
        $approved_saldo = 0;
        $pending_saldo = 0;

        foreach ($items as $item) {
            $commission = isset($item['commission']) ? (float) str_replace(',', '.', $item['commission']) : 0;
            $status = $item['status'] ?? '';
            $approved_for_payment = $item['approvedForPayment'] ?? false;
            $status_final = $item['statusFinal'] ?? false;

            if ($status === 'Geaccepteerd' && !$approved_for_payment && !$status_final) {
                $approved_saldo += $commission;
            }

            if ($status === 'Open' && !$approved_for_payment && !$status_final) {
                $pending_saldo += $commission;
            }
        }

        return [
            'approved' => $approved_saldo,
            'pending' => $pending_saldo,
            'total' => $approved_saldo + $pending_saldo,
        ];
    }
}
