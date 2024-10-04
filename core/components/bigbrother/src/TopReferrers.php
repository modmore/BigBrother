<?php
namespace modmore\BigBrother;


use Google\Analytics\Data\V1beta\DateRange;
use Google\Analytics\Data\V1beta\Dimension;
use Google\Analytics\Data\V1beta\Filter\StringFilter;
use Google\Analytics\Data\V1beta\FilterExpression;
use Google\Analytics\Data\V1beta\Metric;
use Google\Analytics\Data\V1beta\OrderBy;
use Google\Analytics\Data\V1beta\Filter;
use Google\Analytics\Data\V1beta\OrderBy\MetricOrderBy;

class TopReferrers extends BaseReport
{
    public function run(array $params = []): array
    {
        $cacheKey = "reports/{$this->property}-top-referrers";
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
            'dimensions' => [
                new Dimension([
                    'name' => 'sessionSource',
                ]),
            ],
            'metrics' => [
                new Metric([
                    'name' => 'screenPageViews',
                ]),
            ],
            'orderBys' => [
                new OrderBy([
                    'metric' => new MetricOrderBy([
                        'metric_name' => 'screenPageViews'
                    ]),
                    'desc' => true,
                ])
            ],

            'dimensionFilter' => new FilterExpression([
                'filter' => new Filter([
                    'field_name' => 'sessionMedium',
                    'string_filter' => new StringFilter([
                        'value' => 'referral'
                    ])
                ])
            ]),
            'limit' => 50
        ]);

        $data = $this->parseReportToArray($response);
        //return $data;

        $output = [];
        foreach ($data as $value) {

            $isLatest = $value['dateRange'] === 'date_range_0';
            if (!isset($output[$value['sessionSource']])) {
                $output[$value['sessionSource']] = [
                    'title' => $value['sessionSource'],
                    'value' => 0,
                    'previous' => 0,
                ];
            }

            $output[$value['sessionSource']][$isLatest ? 'value' : 'previous'] += (int)$value['screenPageViews'];
        }

        foreach ($output as $key => $values) {
            $output[$key]['improved'] = $values['value'] > $values['previous'];
        }

        uasort($output, static function ($a, $b) {
            return $a['value'] > $b['value'] ? -1 : 1;
        });
        $output = array_values($output);

        $this->cacheManager->set($cacheKey, $output, 3600, \BigBrother::$cacheOptions);
        return $output;
    }

}
