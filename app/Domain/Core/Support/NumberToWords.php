<?php

namespace App\Domain\Core\Support;

/**
 * Conversão de valores numéricos para texto em Português (usado no
 * "valor por extenso" dos documentos fiscais angolanos).
 */
final class NumberToWords
{
    private const UNITS = [
        '', 'um', 'dois', 'três', 'quatro', 'cinco', 'seis', 'sete', 'oito', 'nove',
        'dez', 'onze', 'doze', 'treze', 'catorze', 'quinze', 'dezasseis',
        'dezassete', 'dezoito', 'dezanove',
    ];

    private const TENS = [
        '', '', 'vinte', 'trinta', 'quarenta', 'cinquenta',
        'sessenta', 'setenta', 'oitenta', 'noventa',
    ];

    private const HUNDREDS = [
        '', 'cento', 'duzentos', 'trezentos', 'quatrocentos', 'quinhentos',
        'seiscentos', 'setecentos', 'oitocentos', 'novecentos',
    ];

    private const SCALES = ['', 'mil', 'milhão', 'mil milhões', 'bilião'];

    /**
     * Devolve o valor por extenso em Kwanzas.
     * Ex.: 142440.00 -> "cento e quarenta e dois mil quatrocentos e quarenta kwanzas".
     */
    public static function money(
        float $value,
        string $currencySingular = 'kwanza',
        string $currencyPlural = 'kwanzas',
        string $centSingular = 'cêntimo',
        string $centPlural = 'cêntimos',
    ): string {
        $totalCents = (int) round(abs($value) * 100);
        $integer = intdiv($totalCents, 100);
        $cents = $totalCents % 100;

        $integerWords = self::integer($integer);
        $integerWords = $integerWords === '' ? 'zero' : $integerWords;

        $currency = $integer === 1 ? $currencySingular : $currencyPlural;
        $text = "{$integerWords} {$currency}";

        if ($cents > 0) {
            $centWords = self::integer($cents);
            $centLabel = $cents === 1 ? $centSingular : $centPlural;
            $text .= " e {$centWords} {$centLabel}";
        }

        return ucfirst($text);
    }

    /**
     * Converte um inteiro (>= 0) para texto.
     */
    public static function integer(int $number): string
    {
        if ($number === 0) {
            return 'zero';
        }

        if ($number === 100) {
            return 'cem';
        }

        $groups = [];
        while ($number > 0) {
            $groups[] = $number % 1000;
            $number = intdiv($number, 1000);
        }

        $parts = [];
        for ($i = count($groups) - 1; $i >= 0; $i--) {
            $groupValue = $groups[$i];
            if ($groupValue === 0) {
                continue;
            }

            $groupWords = self::hundreds($groupValue);

            if ($i === 0) {
                $parts[] = $groupWords;
            } elseif ($i === 1) {
                $parts[] = $groupValue === 1 ? 'mil' : "{$groupWords} mil";
            } else {
                $scale = self::SCALES[$i] ?? '';
                $parts[] = $groupValue === 1 ? $scale : "{$groupWords} {$scale}";
            }
        }

        return self::join($parts);
    }

    private static function hundreds(int $number): string
    {
        $parts = [];

        $hundreds = intdiv($number, 100);
        $remainder = $number % 100;

        if ($hundreds > 0) {
            $parts[] = self::HUNDREDS[$hundreds];
        }

        if ($remainder > 0) {
            if ($remainder < 20) {
                $parts[] = self::UNITS[$remainder];
            } else {
                $tens = intdiv($remainder, 10);
                $units = $remainder % 10;
                $parts[] = $units > 0
                    ? self::TENS[$tens] . ' e ' . self::UNITS[$units]
                    : self::TENS[$tens];
            }
        }

        return self::join($parts);
    }

    /**
     * Junta as partes com "e" apenas quando a última parte é < 100 ou >= 1000.
     *
     * @param  array<int, string>  $parts
     */
    private static function join(array $parts): string
    {
        $count = count($parts);

        if ($count === 0) {
            return '';
        }

        if ($count === 1) {
            return $parts[0];
        }

        $text = $parts[0];
        for ($i = 1; $i < $count; $i++) {
            $separator = self::needsConjunction($parts[$i]) ? ' e ' : ' ';
            $text .= $separator . $parts[$i];
        }

        return $text;
    }

    private static function needsConjunction(string $part): bool
    {
        // Usa "e" antes de grupos com menos de 100 ou terminados em "mil".
        return ! str_contains($part, 'milhão')
            && ! str_contains($part, 'mil milhões')
            && ! str_contains($part, 'bilião');
    }
}
