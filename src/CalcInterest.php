<?php
/**
 *
 * User: Colin <zhenhua2340@163.com>
 * Date: 2019/6/8
 */

namespace Colin\FinanceTools;

use Carbon\Carbon;

class CalcInterest
{
    public $baseDays = 360;

    public $baseMonths = 12;

    public $precision = 2;

    public $periodType = 1;  //1、天、2-月

    public $start_time;

    public function __construct($start_time = false)
    {
        $this->start_time = empty($start_time) ? Carbon::now() : Carbon::createFromFormat('Y-m-d H:i:s', $start_time);
    }

    public function setBaseDays($days)
    {
        $this->baseDays = $days;
        return $this;
    }

    public function setBaseMonths($months)
    {
        $this->baseMonths = $months;
        return $this;
    }

    public function setPrecision($precision)
    {
        $this->precision = $precision;
        return $this;
    }

    public function setPeriodType($periodType = 1)
    {
        $this->periodType = intval($periodType);
        return $this;
    }

    public function setStartTime($start_time, $format = 'Y-m-d H:i:s')
    {
        $this->start_time = empty($start_time) ? Carbon::now() : Carbon::createFromFormat($format, $start_time);
        return $this;
    }

    /**
     * 四舍六入五成双
     * @param $num
     * @return mixed
     */
    public function formatPrecision($num)
    {
        $pow = pow(10, $this->precision);
        if (floor($num * $pow * 10) % 10 == 5 && (floor($num * $pow * 10) == $num * $pow * 10) && (floor($num * $pow) % 2 == 0)) {
            return floor($num * $pow) / $pow;
        }
        return round($num, $this->precision);
    }

    /**
     * 一次性还本付息
     * @param $money
     * @param $rate
     * @param $times
     * @return array
     */
    public function oneTimeRepayment($money, $rate, $times)
    {
        if ($this->periodType === 1) {
            $interest = $money * $rate / $this->baseDays * $times;
            $repay_date = $this->start_time->addDays($times);
        } elseif ($this->periodType === 2) {
            $interest = $money * $rate / $this->baseMonths * $times;
            $repay_date = $this->start_time->addMonths($times);
        }
        $interest = $this->formatPrecision($interest);
        return [
            'repay_capital' => $money,
            'repay_interest' => sprintf('%.2f', $interest),
            'repay_money' => bcadd($money, $interest, $this->precision),
            'repay_time' => $repay_date->toDateTimeString(),
            'repay_date' => $repay_date->toDateString(),
        ];
    }

    /**
     * 先息后本
     * @param $money
     * @param $rate
     * @param $months
     * @return array
     */
    public function firstInterest($money, $rate, $months)
    {
        $month_interest = $this->formatPrecision($money * $rate / $this->baseMonths);
        $lists = [];
        for ($i = 1; $i <= $months; $i++) {
            $repay_capital = ($i == $months) ? $money : 0;
            $dataTime = $this->start_time->addMonths(1);
            $lists[] = [
                'repay_capital' => $repay_capital,
                'repay_interest' => $month_interest,
                'repay_money' => bcadd($repay_capital, $month_interest, $this->precision),
                'repay_time' => $dataTime->toDateTimeString(),
                'repay_date' => $dataTime->toDateString()
            ];
        }
        return $lists;
    }

    /**
     * 等额本金
     * @param $money
     * @param $rate
     * @param $months
     * @return array
     */
    public function equivalentCapital($money, $rate, $months)
    {
        $repay_capital = $this->formatPrecision($money / $months);
        $lists = [];
        $alreadycapital = 0;
        for ($i = 1; $i <= $months; $i++) {
            $month_interest = ($money - $repay_capital * ($i - 1)) * ($rate / $this->baseMonths);
            $repay_capital = ($i == $months) ? ($money - $alreadycapital) : $repay_capital;
            $dataTime = $this->start_time->addMonths(1);
            $lists[] = [
                'repay_capital' => $repay_capital,
                'repay_interest' => $this->formatPrecision($month_interest),
                'repay_money' => bcadd($repay_capital, $month_interest, $this->precision),
                'repay_time' => $dataTime->toDateTimeString(),
                'repay_date' => $dataTime->toDateString()
            ];
            $alreadycapital = bcadd($alreadycapital, $repay_capital, $this->precision);
        }
        return $lists;
    }

    /**
     * 等额本息
     * @param $money
     * @param $rate
     * @param $months
     * @return array
     */
    public function equivalentRepayment($money, $rate, $months)
    {
        $month_rate = $rate / $this->baseMonths;
        $month_repay = $money * $month_rate * pow(1 + $month_rate, $months) / (pow(1 + $month_rate, $months) - 1);
        $lists = [];
        $alreadycapital = 0;
        for ($i = 1; $i <= $months; $i++) {
            $item = ['repay_money' => $this->formatPrecision($month_repay)];
            if ($i == 1) {
                $item['repay_interest'] = $this->formatPrecision($money * ($rate / $this->baseMonths));
                $item['repay_capital'] = bcsub($item['repay_money'], $item['repay_interest'], $this->precision);

            } elseif($i == $months) {
                $item['repay_capital'] = bcsub($money, $alreadycapital, $this->precision);
                $item['repay_interest'] = bcsub($item['repay_money'], $item['repay_capital'], $this->precision);

            } else {
                $item['repay_interest'] = $this->formatPrecision(($money - $alreadycapital) * ($rate / $this->baseMonths));
                $item['repay_capital'] = bcsub($item['repay_money'], $item['repay_interest'], $this->precision);
            }

            $item['repay_time'] = $this->start_time->addMonths(1)->toDateTimeString();
            $item['repay_date'] = $this->start_time->addMonths(1)->toDateString();
            $alreadycapital = bcadd($alreadycapital, $item['repay_capital'], $this->precision);
            $lists[] = $item;
        }
        return $lists;
    }

    public function countCapitalAndInterest($lists)
    {
        $data = ['repay_money'=>0, 'repay_capital'=>0, 'repay_interest'=>0];
        foreach ($lists as $item) {
            $data['repay_money'] += $item['repay_money'];
            $data['repay_capital'] += $item['repay_capital'];
            $data['repay_interest'] += $item['repay_interest'];
        }
        return $data;
    }


}