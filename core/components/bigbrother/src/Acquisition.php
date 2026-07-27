<?php
namespace modmore\BigBrother;


use Google\Analytics\Data\V1beta\DateRange;
use Google\Analytics\Data\V1beta\Dimension;
use Google\Analytics\Data\V1beta\Metric;
use Google\Analytics\Data\V1beta\OrderBy;
use Google\Analytics\Data\V1beta\OrderBy\DimensionOrderBy;

class Acquisition extends BaseReport
{
    public function run(array $params = []): array
    {
        $cacheKey = "reports/{$this->property}-acquisition";
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
                    'name' => 'firstUserMedium',
                ]),
            ],
            'metrics' => [
                new Metric([
                    'name' => 'screenPageViews',
                ]),
            ],
            'orderBys' => [
                new OrderBy([
                    'dimension' => new DimensionOrderBy([
                        'dimension_name' => 'firstUserMedium'
                    ])
                ])
            ]
        ]);

        $data = $this->parseReportToArray($response);

        // Join both date ranges on medium name so Chart.js can align them by index.
        $byMedium = [];
        foreach ($data as $value) {
            $medium = $this->normalizeMedium($value['firstUserMedium']);
            $dataset = $value['dateRange'] === 'date_range_0' ? 0 : 1;
            if (!isset($byMedium[$medium])) {
                $byMedium[$medium] = [0 => 0, 1 => 0];
            }
            $byMedium[$medium][$dataset] += (int)$value['screenPageViews'];
        }

        if (empty($byMedium)) {
            return [];
        }

        uasort($byMedium, static function ($a, $b) {
            return $b[0] <=> $a[0];
        });

        $labels = [];
        $data0 = [];
        $data1 = [];
        foreach ($byMedium as $medium => $values) {
            $labels[] = $medium;
            $data0[] = $values[0];
            $data1[] = $values[1];
        }

        $output = [
            0 => ['labels' => $labels, 'data' => $data0],
            1 => ['labels' => $labels, 'data' => $data1],
        ];

        $this->cacheManager->set($cacheKey, $output, 3600, \BigBrother::$cacheOptions);
        return $output;
    }

    private function normalizeMedium(string $firstUserMedium): string
    {
        if ($firstUserMedium === '(none)') {
            return 'direct';
        }
        return $firstUserMedium;
    }
}
