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

        $output = [];
        foreach ($data as $value) {
            $dataset = $value['dateRange'] === 'date_range_0' ? 0 : 1;
            $output[$dataset]['labels'][] = $this->normalizeMedium($value['firstUserMedium']);
            $output[$dataset]['data'][] = (int)$value['screenPageViews'];
        }

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
