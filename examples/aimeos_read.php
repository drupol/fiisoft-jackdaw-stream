<?php declare(strict_types=1);

use Aimeos\Map;

require_once  __DIR__ .'/../vendor/autoload.php';

$timeStart = microtime(true);
$memoryStart = memory_get_usage();

$reader = function ($fp): \Generator {
    $index = 0;
    $line = fgets($fp);
    while ($line !== false) {
        yield $index++ => $line;
        $line = fgets($fp);
    }
};

$data = [];

foreach ($reader(fopen(__DIR__.'/../var/testfile.txt', 'rb')) as $line) {
    $row = Map::from([$line])
        ->map(static fn(string $line) => json_decode($line, true, 512, JSON_THROW_ON_ERROR))
        ->filter(static fn(array $row) => $row['isVerified'])
        ->filter(static fn(array $row) => isset($row['facebookId']))
        ->filter(static fn(array $row) => $row['credits'] >= 500000)
        ->filter(static fn(array $row) => $row['scoring'] >= 95.0)
        ->filter(static fn(array $row) => mb_strlen($row['name']) === 10)
        ->map(static fn(array $row) => ['id' => $row['id'], 'credits' => $row['credits']])
        ->toArray();
    
    if (!empty($row)) {
        $data[] = $row[0];
    }
}

$iterator = Map::from($data)
    ->usort(static fn(array $a, array $b) => $b['credits'] <=> $a['credits'] ?: $a['id'] <=> $b['id'])
    ->slice(0, 20);

echo 'best 20 rows: ', PHP_EOL;

foreach ($iterator as $row) {
    echo 'id: ', $row['id'],' credits: ', $row['credits'], PHP_EOL;
}

echo PHP_EOL, 'total found rows: ', \count($data), PHP_EOL;

$memoryStop = memory_get_usage();
$timeStop = microtime(true);

echo 'memory usage: ', $memoryStop - $memoryStart, ' (peak: ', memory_get_peak_usage(true), ')', PHP_EOL,
'execution time: ', ($timeStop - $timeStart), PHP_EOL;