<?php

namespace App\Services\AI;

class TextNormalizer
{
    protected static array $numberMap = [
        'zero'      => 0, 'one'       => 1, 'two'       => 2, 'three'     => 3, 'four'      => 4,
        'five'      => 5, 'six'       => 6, 'seven'     => 7, 'eight'     => 8, 'nine'      => 9,
        'ten'       => 10, 'eleven'    => 11, 'twelve'    => 12, 'thirteen'  => 13,
        'fourteen'  => 14, 'fifteen'   => 15, 'sixteen'   => 16, 'seventeen' => 17,
        'eighteen'  => 18, 'nineteen'  => 19, 'twenty'    => 20, 'thirty'    => 30,
        'forty'     => 40, 'fifty'     => 50, 'sixty'     => 60, 'seventy'   => 70,
        'eighty'    => 80, 'ninety'    => 90, 'hundred'   => 100, 'thousand' => 1000
    ];

    public function normalize(string $text): string
    {
        $text = trim($text);
        
        // Remove trailing or leading punctuations but preserve words and digits
        $text = preg_replace('/[^\w\s\.]/', '', $text);
        
        $words = explode(' ', strtolower($text));
        $normalizedWords = [];
        
        foreach ($words as $word) {
            $word = trim($word);
            if (empty($word)) continue;
            
            if (isset(self::$numberMap[$word])) {
                $normalizedWords[] = self::$numberMap[$word];
            } else {
                $normalizedWords[] = $word;
            }
        }
        
        $result = implode(' ', $normalizedWords);
        
        // Combined numbers handling (e.g. "2 100" -> 200, "1 1000" -> 1000)
        $result = preg_replace_callback('/(\d+)\s+1000/i', function ($m) {
            return (int)$m[1] * 1000;
        }, $result);
        
        $result = preg_replace_callback('/(\d+)\s+100/i', function ($m) {
            return (int)$m[1] * 100;
        }, $result);

        return $result;
    }
}
