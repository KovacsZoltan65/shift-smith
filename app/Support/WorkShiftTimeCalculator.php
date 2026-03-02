<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

final class WorkShiftTimeCalculator
{
    public static function parseToMinutes(string $time): int
    {
        if (! preg_match('/^(?<h>[01]\d|2[0-3]):(?<m>[0-5]\d)$/', $time, $matches)) {
            throw new InvalidArgumentException("Invalid time format: {$time}");
        }

        return ((int) $matches['h'] * 60) + (int) $matches['m'];
    }

    /**
     * @return array{start:int,end:int,duration:int}
     */
    public static function shiftWindow(string $startTime, string $endTime): array
    {
        $start = self::parseToMinutes($startTime);
        $end = self::parseToMinutes($endTime);

        if ($end < $start) {
            $end += 1440;
        }

        if ($end === $start) {
            throw new InvalidArgumentException('Shift end time must differ from start time.');
        }

        return [
            'start' => $start,
            'end' => $end,
            'duration' => $end - $start,
        ];
    }

    /**
     * @param list<array{break_start_time:string,break_end_time:string}> $breaks
     * @return array{
     *   total:int,
     *   rows:list<array{break_start_time:string,break_end_time:string,break_minutes:int}>,
     *   intervals:list<array{start:int,end:int,index:int}>
     * }
     */
    public static function calculateBreaks(array $breaks, string $shiftStartTime, string $shiftEndTime): array
    {
        $window = self::shiftWindow($shiftStartTime, $shiftEndTime);
        $shiftStart = $window['start'];
        $shiftEnd = $window['end'];
        $isOvernight = self::parseToMinutes($shiftEndTime) < self::parseToMinutes($shiftStartTime);

        $rows = [];
        $intervals = [];
        $total = 0;

        foreach ($breaks as $index => $break) {
            $start = self::parseToMinutes($break['break_start_time']);
            $end = self::parseToMinutes($break['break_end_time']);

            if ($end <= $start) {
                $end += 1440;
            }

            $duration = $end - $start;
            if ($duration <= 0) {
                throw new InvalidArgumentException("Break #{$index} has invalid duration.");
            }

            $alignedStart = $start;
            while ($alignedStart < $shiftStart) {
                $alignedStart += 1440;
            }

            $alignedEnd = $alignedStart + $duration;
            if ($alignedStart < $shiftStart || $alignedEnd > $shiftEnd) {
                $breakStartLabel = $break['break_start_time'];
                $breakEndLabel = $break['break_end_time'];
                $overnightHint = $isOvernight
                    ? ' Overnight shift esetén a szünetnek a műszak vége elé kell esnie.'
                    : '';
                throw new InvalidArgumentException(
                    "Break #{$index} ({$breakStartLabel}–{$breakEndLabel}) is outside shift ({$shiftStartTime}–{$shiftEndTime})."
                    .' A műszak vége kizáró határ, oda nem kezdődhet szünet.'
                    .$overnightHint
                );
            }

            $rows[] = [
                'break_start_time' => $break['break_start_time'],
                'break_end_time' => $break['break_end_time'],
                'break_minutes' => $duration,
            ];
            $intervals[] = [
                'start' => $alignedStart,
                'end' => $alignedEnd,
                'index' => $index,
            ];
            $total += $duration;
        }

        usort($intervals, static fn (array $a, array $b): int => $a['start'] <=> $b['start']);
        for ($i = 1, $len = count($intervals); $i < $len; $i++) {
            if ($intervals[$i]['start'] < $intervals[$i - 1]['end']) {
                throw new InvalidArgumentException(
                    "Break intervals overlap ({$intervals[$i - 1]['index']} and {$intervals[$i]['index']})."
                );
            }
        }

        return [
            'total' => $total,
            'rows' => $rows,
            'intervals' => $intervals,
        ];
    }
}
