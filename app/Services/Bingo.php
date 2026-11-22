<?php

namespace App\Services;

use Carbon\Carbon;

/**
 * A weekly bingo card of countries. A square lights up when one of your sites had a visitor from there in the last 7 days.
 */
class Bingo
{
    /** Countries a card is drawn from: likely enough to turn up, varied enough to be fun. */
    public const POOL = [
        'US', 'GB', 'NL', 'DE', 'FR', 'ES', 'IT', 'CA', 'AU', 'IN', 'BR', 'UG', 'KE', 'NG', 'ZA', 'JP', 'CN', 'BE', 'SE', 'NO',
        'PL', 'PT', 'IE', 'CH', 'AT', 'MX', 'AR', 'TR', 'RU', 'UA', 'SG', 'ID', 'PH', 'EG', 'TZ', 'GH', 'NZ', 'FI', 'DK', 'KR',
    ];

    public function __construct(private GoogleAnalytics $analytics) {}

    /**
     * @param  array<string, int>  $visitors  Users per country code.
     * @return array{rows: array<int, array<int, array{code: string, name: string, lit: bool, free: bool}>>, lit: int, lines: int, week: string}
     */
    public function card(array $visitors, ?\DateTimeInterface $at = null): array
    {
        $at = $at ? Carbon::instance($at) : now();
        $names = $this->analytics->worldMap()['countries'];
        $codes = array_values(array_filter(self::POOL, fn (string $code) => isset($names[$code])));

        mt_srand(crc32($at->format('o-W')));
        $picked = $codes;
        for ($i = count($picked) - 1; $i > 0; $i--) {
            $j = mt_rand(0, $i);
            [$picked[$i], $picked[$j]] = [$picked[$j], $picked[$i]];
        }
        mt_srand();
        $picked = array_slice($picked, 0, 24);
        array_splice($picked, 12, 0, ['FREE']);

        $rows = [];
        foreach (array_chunk($picked, 5) as $row) {
            $rows[] = array_map(fn (string $code) => $code === 'FREE'
                ? ['code' => 'FREE', 'name' => 'Free', 'lit' => true, 'free' => true]
                : ['code' => $code, 'name' => $names[$code]['name'], 'lit' => ($visitors[$code] ?? 0) > 0, 'free' => false], $row);
        }

        $lit = collect($rows)->flatten(1)->where('lit', true)->where('free', false)->count();

        return ['rows' => $rows, 'lit' => $lit, 'lines' => $this->lines($rows), 'week' => $at->format('o-\WW')];
    }

    /**
     * @param  array<int, array<int, array{lit: bool}>>  $rows
     */
    private function lines(array $rows): int
    {
        $lines = 0;
        for ($i = 0; $i < 5; $i++) {
            $lines += collect(range(0, 4))->every(fn (int $j) => $rows[$i][$j]['lit']) ? 1 : 0;
            $lines += collect(range(0, 4))->every(fn (int $j) => $rows[$j][$i]['lit']) ? 1 : 0;
        }
        $lines += collect(range(0, 4))->every(fn (int $i) => $rows[$i][$i]['lit']) ? 1 : 0;
        $lines += collect(range(0, 4))->every(fn (int $i) => $rows[$i][4 - $i]['lit']) ? 1 : 0;

        return $lines;
    }
}
