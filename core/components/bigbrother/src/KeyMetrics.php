<?php
namespace modmore\BigBrother;


use Google\Analytics\Data\V1beta\DateRange;
use Google\Analytics\Data\V1beta\Metric;

class KeyMetrics extends BaseReport
{
    public function run(array $params = []): array
    {
        $cacheKey = "reports/{$this->property}-key-metrics";
        if ($data = $this->cacheManager->get($cacheKey, \BigBrother::$cacheOptions)) {
            return $data;
        }

        $response = $this->client->runReport([
            'property' => 'properties/' . $this->property,
            'dateRanges' => [
                new DateRange([
                    'start_date' => '28daysAgo',
                    'end_date' => 'today',
                ]),
                new DateRange([
                    'start_date' => '56daysAgo',
                    'end_date' => '28daysAgo',
                ]),
            ],
            'metrics' => [
                new Metric([
                    'name' => 'sessions',
                ]),
                new Metric([
                    'name' => 'screenPageViews',
                ]),
                new Metric([
                    'name' => 'activeUsers',
                ]),
                new Metric([
                    'name' => 'engagementRate',
                ]),
                new Metric([
                    'name' => 'avgUserEngagementDuration',
                    'expression' => 'userEngagementDuration / totalUsers',
                ]),
            ],
        ]);

        $data = $this->parseReportToArray($response);

        $output = [];
        foreach ($data[0] as $metric => $value) {
            $output[$metric] = [
                'value' => $value,
                'label' => $metric,
                'previous' => 0,
            ];
        }
        if (isset($data[1])) {
            foreach ($data[1] as $metric => $previous) {
                $output[$metric]['previous'] = $previous;
            }
        }

        // Output as an iterable array while applying appropriate formatting to each metric
        // Note any metric without a `processMetricName` method will be skipped
        $output = $this->processMetrics($output);

        $this->cacheManager->set($cacheKey, $output, 3600, \BigBrother::$cacheOptions);

        return $output;
    }

    private function processMetrics(array $metrics)
    {
        $output = [];
        foreach ($metrics as $key => $metric) {
            $mn = 'process' . ucfirst($key);
            if (method_exists($this, $mn)) {
                $output[] = $this->{$mn}($metric);
            }
        }
        return $output;
    }

    private function processSessions(array $metric)
    {
        return [
            'label' => $this->modx->lexicon('bigbrother.metrics.sessions'),
            'value' => number_format($metric['value']),
            'previous' => number_format($metric['previous']),
            'improved' => $metric['value'] > $metric['previous'],
        ];
    }

    private function processScreenPageViews(array $metric)
    {
        return [
            'label' => $this->modx->lexicon('bigbrother.metrics.page_views'),
            'value' => number_format($metric['value']),
            'previous' => number_format($metric['previous']),
            'improved' => $metric['value'] > $metric['previous'],
        ];
    }

    private function processActiveUsers(array $metric)
    {
        return [
            'label' => $this->modx->lexicon('bigbrother.metrics.unique_visitors'),
            'value' => number_format($metric['value']),
            'previous' => number_format($metric['previous']),
            'improved' => $metric['value'] > $metric['previous'],
        ];
    }
    private function processEngagementRate(array $metric)
    {
        return [
            'label' => $this->modx->lexicon('bigbrother.metrics.engagement_rate'),
            'value' => number_format($metric['value'] * 100, 1) . '%',
            'previous' => number_format($metric['previous'] * 100, 1) . '%',
            'improved' => $metric['value'] > $metric['previous'],
        ];
    }

    private function processAvgUserEngagementDuration(array $metric)
    {
        return [
            'label' => $this->modx->lexicon('bigbrother.metrics.avg_time_on_site'),
            'value' => $this->formatSeconds($metric['value']),
            'previous' => $this->formatSeconds($metric['previous']),
            'improved' => $metric['value'] > $metric['previous'],
        ];
    }

    private function formatSeconds(int $seconds): string
    {
        return sprintf("%02d%s%02d", floor($seconds/60), ':', $seconds%60);
    }
}
