<?php

declare(strict_types=1);

/**
 * Time two fixed pieces of work that do not depend on the framework, run through `wp eval`.
 *
 * The nightly timings (boot, thumbnail) move with the machine the job lands on: the same commit
 * measured 40 ms one night and 73 ms the next. These two numbers say how fast the machine was:
 * `cpu_ref_ms` is a chain of hashes (processor), `fs_ref_ms` writes and reads a few hundred small
 * files (disk and file system). They are recorded with the metrics and printed by the comparison,
 * never judged: they exist to learn whether the timings can be told apart from the machine.
 */
$start = hrtime(true);
$digest = '';

for ($i = 0; $i < 250_000; $i++) {
    $digest = hash('sha256', $digest.$i);
}

$cpu = (hrtime(true) - $start) / 1_000_000;

$directory = sys_get_temp_dir().'/e2e-perf-calibration-'.bin2hex(random_bytes(4));
mkdir($directory);

$start = hrtime(true);

for ($i = 0; $i < 400; $i++) {
    file_put_contents($directory.'/'.$i.'.txt', str_repeat('x', 2048));
}

$read = 0;

for ($i = 0; $i < 400; $i++) {
    $read += strlen((string) file_get_contents($directory.'/'.$i.'.txt'));
}

$disk = (hrtime(true) - $start) / 1_000_000;

for ($i = 0; $i < 400; $i++) {
    unlink($directory.'/'.$i.'.txt');
}

rmdir($directory);

if ($read !== 400 * 2048) {
    throw new RuntimeException('The calibration read back less than it wrote: the measure would be of nothing.');
}

echo json_encode(['cpu_ref_ms' => round($cpu, 1), 'fs_ref_ms' => round($disk, 1)], JSON_THROW_ON_ERROR);
