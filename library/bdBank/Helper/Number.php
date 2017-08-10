<?php

class bdBank_Helper_Number
{
    public static function add($a, $b)
    {
        if (function_exists('bcadd')) {
            return bcadd($a, $b, bdBank_Model_Bank::options('balanceDecimals'));
        } else {
            return (doubleval($a) + doubleval($b));
        }
    }

    public static function sub($a, $b)
    {
        if (function_exists('bcsub')) {
            return bcsub($a, $b, bdBank_Model_Bank::options('balanceDecimals'));
        } else {
            return (doubleval($a) - doubleval($b));
        }
    }

    public static function mul($a, $b)
    {
        if (function_exists('bcmul')) {
            return bcmul($a, $b, bdBank_Model_Bank::options('balanceDecimals'));
        } else {
            return (doubleval($a) * doubleval($b));
        }
    }

    public static function min_($a, $b)
    {
        if (self::comp($a, $b) === 1) {
            return $b;
        } else {
            return $a;
        }
    }

    public static function max_($a, $b)
    {
        if (self::comp($a, $b) === 1) {
            return $a;
        } else {
            return $b;
        }
    }

    public static function comp($a, $b)
    {
        if (function_exists('bccomp')) {
            return bccomp($a, $b, bdBank_Model_Bank::options('balanceDecimals'));
        } else {
            $a = doubleval($a);
            $b = doubleval($b);

            if ($a === $b) {
                return 0;
            } elseif ($a > $b) {
                return 1;
            } else {
                return -1;
            }
        }
    }
}
