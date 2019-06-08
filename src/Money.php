<?php
/**
 *
 * User: Colin <zhenhua2340@163.com>
 * Date: 2019/6/8
 */
namespace Colin\FinanceTools;

class Money
{
    public static $numberMap = [
        0 => '零',
        1 => '壹',
        2 => '贰',
        3 => '叁',
        4 => '肆',
        5 => '伍',
        6 => '陆',
        7 => '柒',
        8 => '捌',
        9 => '玖',
        '-' => '负',
        '.' => '',
    ];

    public static $unitMap = [
        '拾',
        '佰',
        '仟',
        '万',
        '亿',
    ];

    public static $monayUnitMap = [
        '圆',
        '角',
        '分',
        '厘',
        '毫',
    ];

    /**
     * 数字转为中文金额大写
     *
     * @param string $number
     * @param array $options
     * @return string
     */
    public static function toChinese($number, $options = [])
    {
        if (!is_numeric($number)) {
            throw new \InvalidArgumentException(sprintf('%s is not a valied number', $number));
        }

        list($integer, $decimal) = explode('.', $number . '.');

        if ($integer < 0) {
            $pom = self::$numberMap['-'];
            $integer = abs($integer);
        } else {
            $pom = '';
        }

        return $pom . self::parseInteger($integer, $options) . self::parseDecimal($decimal, $options);
    }

    /**
     * 处理整数部分
     *
     * @param string $number
     * @return string
     */
    private static function parseInteger($number)
    {
        // 准备数据，分割为4个数字一组
        $length = strlen($number);
        $firstItems = $length % 4;
        $leftStr = substr($number, $firstItems);
        if ('' === $leftStr) {
            $split4 = [];
        } else {
            $split4 = str_split($leftStr, 4);
        }
        if ($firstItems > 0) {
            array_unshift($split4, substr($number, 0, $firstItems));
        }
        $split4Count = count($split4);

        $unitIndex = ($length - 1) / 4 >> 0;

        if (0 === $unitIndex) {
            $unitIndex = $firstItems - 2;
        } else {
            $unitIndex += 2;
        }

        $result = '';
        foreach ($split4 as $i => $item) {
            $index = $unitIndex - $i;

            $length = isset($item[3]) ? 4 : strlen($item);

            $itemResult = '';
            $has0 = false;
            for ($j = 0; $j < $length; ++$j) {
                if (0 == $item[$j]) {
                    $has0 = true;
                } else {
                    if ($has0) {
                        $itemResult .= self::$numberMap[0];
                        $has0 = false;
                    }
                    $itemResult .= self::$numberMap[$item[$j]];
                    if (isset(self::$unitMap[$length - $j - 2])) {
                        $itemResult .= self::$unitMap[$length - $j - 2];
                    }
                }
            }
            if ('' === $itemResult) {
                if (isset(self::$unitMap[$index])) {
                    if ($index > 3) {
                        $result .= self::$unitMap[$index];
                    }
                } else if ('0' != $item) {
                    $result .= isset(self::$unitMap[$index + 1]) ? self::$unitMap[$index + 1] : str_repeat(self::$unitMap[3], $index - 3);
                }
            } else {
                if ($i !== $split4Count - 1 && isset(self::$unitMap[$index])) {
                    $unit = self::$unitMap[$index];
                } else {
                    $unit = $index > 4 ? self::$unitMap[3] : '';
                }
                $result .= $itemResult . $unit;
            }
        }
        if ('' !== $result) {
            $result .= self::$monayUnitMap[0];
        }
        return $result;
    }

    /**
     * 处理小数部分
     *
     * @param string $number
     * @return string
     */
    private static function parseDecimal($number)
    {
        if ('' === $number) {
            return '';
        }
        $result = '';
        $number = strrev(intval(strrev($number)));
        $length = strlen($number);
        for ($i = 0; $i < $length; ++$i) {
            if (0 == $number[$i]) {
                $result .= self::$numberMap[$number[$i]];
            } else {
                $result .= self::$numberMap[$number[$i]] . (isset(self::$monayUnitMap[$i + 1]) ? self::$monayUnitMap[$i + 1] : '');
            }
        }
        return $result;
    }

    /**
     * 中文金额大写转数字
     *
     * @param string $text
     * @return string
     */
    public static function toNumber($text)
    {
        $length = mb_strlen($text);
        $number = $partNumber = $lastNum = $decimal = 0;
        $pom = 1; // 正数或负数，1或-1
        $isDecimal = false === strpos($text, self::$monayUnitMap[0]);
        $scale = count(self::$monayUnitMap) - 1;

        $lastKey = -1;
        for ($i = 0; $i < $length; ++$i) {
            $char = mb_substr($text, $i, 1);
            if (0 === $i && self::$numberMap['-'] === $char) {
                $pom = -1;
                continue;
            }

            $key = array_search($char, self::$numberMap);

            // 小数
            if ($isDecimal) {
                ++$i;
                $unit = mb_substr($text, $i, 1);
                $unitKey = array_search($unit, self::$monayUnitMap);
                if (false === $unitKey) {
                    --$i;
                    $decimal .= $key;
                } else {
                    $decimal = bcadd($decimal, bcmul($key, bcpow(10, -($unitKey), $scale), $scale), $scale);
                }
            } else if (false === $key) {
                $key = array_search($char, self::$unitMap);

                if (false === $key) {
                    $key = array_search($char, self::$monayUnitMap);
                    if (false !== $key) {
                        $isDecimal = true;
                        continue;
                    }
                    throw new \InvalidArgumentException(sprintf('%s is not a valied chinese number text', $text));
                }

                // 单位
                if ($key > 3) {
                    $tNumber = bcpow(10, (($key - 3) * 4) + 4);
                } else {
                    $tNumber = bcpow(10, $key + 1);
                }

                if (null === $lastNum) {
                    $lastNum = 1;
                }

                if ($key > 3 || (3 === $key && $partNumber >= 10)) {
                    if ($key < $lastKey) {
                        $number = bcadd($number, bcmul(bcadd($partNumber, $lastNum, $scale), $tNumber, $scale), $scale);
                    } else {
                        $number = bcmul(bcadd($number, bcadd($partNumber, $lastNum, $scale), $scale), $tNumber, $scale);
                    }
                    $partNumber = 0;
                    $lastNum = null;
                    $lastKey = $key;
                } else {
                    $partNumber = bcadd($partNumber, bcmul($lastNum, $tNumber, $scale), $scale);
                    $lastNum = 0;
                }

            } else {
                $lastNum = $key;
            }
        }
        $result = bcmul(bcadd(bcadd($number, bcadd($partNumber, $lastNum, $scale), $scale), $decimal, $scale), $pom, $scale);
        if (false === strpos($result, '.')) {
            return $result;
        } else {
            return rtrim(rtrim($result, '0'), '.');
        }
    }
}