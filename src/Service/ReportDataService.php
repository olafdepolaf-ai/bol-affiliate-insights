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
        $end_date = new \DateTimeImmutable('yesterday', wp_timezone());
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

        if ($metric === 'commission') {
            $response = $this->api_client->get_orders_report($start_date_str, $end_date_str);
            $date_key = 'orderDateTime';
        } else {
            $response = $this->api_client->get_promotion_methods_report($start_date_str, $end_date_str);
            $date_key = 'date';
        }

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

        return $this->aggregate_chart_data($items, $metric, $granularity, $start_date, $end_date, $date_key);
    }

    private function aggregate_chart_data($items, $metric, $granularity, $start_date, $end_date, $date_key) {
        $aggregated_data = [];
        $current_date = clone $start_date;

        while ($current_date <= $end_date) {
            $label = '';
            $key = '';

            switch ($granularity) {
                case 'day':
                    $label = $current_date->format('M d');
                    $key = $current_date->format('Y-m-d');
                    $current_date = $current_date->modify('+1 day');
                    break;
                case 'week':
                    $label = 'Week ' . $current_date->format('W');
                    $key = $current_date->format('Y-W');
                    $current_date = $current_date->modify('+1 week');
                    break;
                case 'month':
                    $label = $current_date->format('M Y');
                    $key = $current_date->format('Y-m');
                    $current_date = $current_date->modify('+1 month');
                    break;
            }
            $aggregated_data[$key] = ['label' => $label, 'value' => 0];
        }

        foreach ($items as $item) {
            $item_date = new \DateTimeImmutable($item[$date_key], wp_timezone());
            $value = 0;

            switch ($metric) {
                case 'commission':
                    // Sum commissionAmount for orders
                    if (isset($item['commissionAmount'])) {
                        $value = (float) $item['commissionAmount'];
                    }
                    break;
                case 'orders':
                    // Count orders
                    $value = 1;
                    break;
                case 'clicks':
                    // Sum clicks
                    if (isset($item['clicks'])) {
                        $value = (int) $item['clicks'];
                    }
                    break;
                case 'revenue':
                    // Sum revenueOriginalInclVat for promotion methods
                    if (isset($item['revenueOriginalInclVat'])) {
                        $value = (float) $item['revenueOriginalInclVat'];
                    }
                    break;
                case 'conversion':
                    // Conversion rate needs total orders and total clicks for the period
                    // This will be calculated later if needed, or passed as a separate metric
                    // For now, we'll just set it to 0 or handle it differently.
                    $value = 0; 
                    break;
                default:
                    $value = 0;
                    break;
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

            if (isset($aggregated_data[$key])) {
                $aggregated_data[$key]['value'] += $value;
            }
        }

        $labels = [];
        $data = [];
        foreach ($aggregated_data as $entry) {
            $labels[] = $entry['label'];
            $data[] = $entry['value'];
        }

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
}
