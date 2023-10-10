<?php

declare(strict_types=1);

namespace Pollen\Query\QueryBuilder;

class DateQueryBuilder extends SubQuery
{
    private $year;

    private $month;

    private $day;

    private $hour;

    private $minute;

    private $second;

    private $after = null;

    private $before = null;

    private $inclusive = true;

    const POST_CREATED = 'post_date';

    const POST_MODIFIED = 'post_modified';

    const ALLOWED_KEYS = ['year', 'month', 'day', 'hour', 'minute', 'second'];

    public function __construct(private $column = 'post_date')
    {
    }

    public function created()
    {
        $this->column = self::POST_CREATED;

        return $this;
    }

    public function modified()
    {
        $this->column = self::POST_MODIFIED;

        return $this;
    }

    private function validateDateArray(array $date)
    {
        foreach ($date as $key => $part) {
            if (! in_array($key, self::ALLOWED_KEYS)) {
                throw new QueryException('Invalid key '.$key.' element supplied.');
            }
            $this->$key = $part;
        }

        return true;
    }

    private function applyDateArray(array $date)
    {
        foreach ($date as $key => $part) {
            $this->$key = $part;
        }
    }

    public function within($date, $extract = 'Ymdhis')
    {

        if (is_array($date) && $this->validateDateArray($date)) {
            $this->applyDateArray($date);
        } else {
            $parts = $this->extractFromDate($date, $extract);
            $this->applyDateArray($parts);
        }

        return $this;
    }

    public function between($fromDate, $toDate)
    {

        $this->after = $this->extractFromDate($fromDate);
        $this->before = $this->extractFromDate($toDate);

        return $this;
    }

    public function before($arg1, $arg2 = null)
    {
        $beforeDate = $arg2 ? $arg2 : $arg1;
        $this->before = $this->extractFromDate($beforeDate);
        $this->before['column'] = $arg2 ? $arg1 : $this->column;

        return $this;
    }

    public function after($arg1, $arg2 = null)
    {
        $afterDate = $arg2 ? $arg2 : $arg1;
        $this->after = $this->extractFromDate($afterDate);
        $this->after['column'] = $arg2 ? $arg1 : $this->column;

        return $this;
    }

    public function extractFromDate($date, $extract = 'Ymdhis')
    {

        if (! is_numeric($date)) {
            $date = strtotime($date);

            if (false === $date) {
                throw new QueryException('Provided datestring '.$date.' could not be converted to time');
            }
        }

        $extracted = [];

        if (false !== strpos($extract, 'Y')) {
            $extracted['year'] = date('Y', $date);
        }
        if (false !== strpos($extract, 'm')) {
            $extracted['month'] = date('m', $date);
        }
        if (false !== strpos($extract, 'd')) {
            $extracted['day'] = date('d', $date);
        }
        if (false !== strpos($extract, 'h')) {
            $extracted['hour'] = date('h', $date);
        }
        if (false !== strpos($extract, 'i')) {
            $extracted['minute'] = date('i', $date);
        }
        if (false !== strpos($extract, 's')) {
            $extracted['second'] = date('s', $date);
        }

        return $extracted;
    }

    public function get()
    {
        $config = [
            'column' => $this->column,
            'year' => $this->year,
            'month' => $this->month,
            'day' => $this->day,
            'hour' => $this->hour,
            'minute' => $this->minute,
            'second' => $this->second,
        ];

        if (! is_null($this->before) && ! is_null($this->after)) {
            unset($config);
            $config = [];
            $config['before'] = $this->before;
            $config['after'] = $this->after;
        } elseif (! is_null($this->before)) {
            unset($config);
            $config = [];
            $config['before'] = $this->before;
        } elseif (! is_null($this->after)) {
            unset($config);
            $config = [];
            $config['after'] = $this->after;
        }

        return $config;
    }
}
