<?php

namespace App\Helpers;

class NumberToWordsHelper
{
    private const ONES = [
        '', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine',
        'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen',
        'Seventeen', 'Eighteen', 'Nineteen',
    ];

    private const TENS = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

    /**
     * Spell an amount using the Indian numbering system (lakh/crore).
     * Whole part reads as rupees; any fractional part is appended as "... and N Paise".
     *
     * ponytail: crore segment uses two-digit words (caps at 99 crore); recurse if ever over.
     */
    public static function inr(float $amount): string
    {
        $rupees = (int) floor($amount);
        $paise  = (int) round(($amount - $rupees) * 100);

        if ($paise === 100) {
            $rupees++;
            $paise = 0;
        }

        $words = self::rupeesToWords($rupees);

        if ($paise > 0) {
            $words .= ' and ' . self::twoDigits($paise) . ' Paise';
        }

        return $words;
    }

    private static function rupeesToWords(int $n): string
    {
        if ($n === 0) {
            return 'Zero';
        }

        $crore = intdiv($n, 10000000);
        $n %= 10000000;
        $lakh = intdiv($n, 100000);
        $n %= 100000;
        $thousand = intdiv($n, 1000);
        $n %= 1000;
        $hundred = intdiv($n, 100);
        $rest = $n % 100;

        $parts = [];
        if ($crore > 0)    { $parts[] = self::twoDigits($crore) . ' Crore'; }
        if ($lakh > 0)     { $parts[] = self::twoDigits($lakh) . ' Lakh'; }
        if ($thousand > 0) { $parts[] = self::twoDigits($thousand) . ' Thousand'; }
        if ($hundred > 0)  { $parts[] = self::ONES[$hundred] . ' Hundred'; }
        if ($rest > 0)     { $parts[] = self::twoDigits($rest); }

        return implode(' ', $parts);
    }

    private static function twoDigits(int $n): string
    {
        if ($n < 20) {
            return self::ONES[$n];
        }

        $tens = self::TENS[intdiv($n, 10)];
        $one = $n % 10;

        return $one > 0 ? $tens . ' ' . self::ONES[$one] : $tens;
    }
}
