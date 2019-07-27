<?php

namespace Sunnysideup\SearchSimpleSmart\Api;

/**
 * Copyright (c) 2005 Richard Heyes (http://www.phpguru.org/)
 *
 * All rights reserved.
 *
 * This script is free software.
 */

/**
 * PHP5 Implementation of the Porter Stemmer algorithm. Certain elements
 * were borrowed from the (broken) implementation by Jon Abernathy.
 *
 * Usage:
 *
 *  $stem = PorterStemmer::Stem($word);
 *
 * How easy is that?
 */
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;

class SearchEngineStemming
{
    use Extensible;
    use Injectable;
    use Configurable;
    use SearchEngineStemmingHelperTrait;

    private const END_REPLACERS = [
        'a' => 'al',
        'a' => 'er',
        'i' => 'ic',
        's' => 'ism',
        'u' => 'ous',
        'v' => 'ive',
        'z' => 'ize',
        'a' => 'er',
    ];


    /**
     * Regex for matching a consonant
     * @var string
     */
    private static $regex_consonant = '(?:[bcdfghjklmnpqrstvwxz]|(?<=[aeiou])y|^y)';

    /**
     * Regex for matching a vowel
     * @var string
     */
    private static $regex_vowel = '(?:[aeiou]|(?<![aeiou])y)';

    /**
     * Stems a word. Simple huh?
     *
     * @param  string $word Word to stem
     * @return string       Stemmed word
     */
    public static function Stem($word)
    {
        if (strlen($word) <= 2) {
            return $word;
        }

        $word = self::step1ab($word);
        $word = self::step1c($word);
        $word = self::step2($word);
        $word = self::step3($word);
        $word = self::step4($word);
        return self::step5($word);
    }

    /**
     * Step 1
     */
    private static function step1ab($word)
    {
        // Part a
        if (substr($word, -1) === 's') {
            self::replace($word, 'sses', 'ss')
            or self::replace($word, 'ies', 'i')
            or self::replace($word, 'ss', 'ss')
            or self::replace($word, 's', '');
        }

        // Part b
        if (substr($word, -2, 1) !== 'e' or ! self::replace($word, 'eed', 'ee', 0)) { // First rule
            $v = self::$regex_vowel;

            // ing and ed
            if (preg_match("#${v}+#", substr($word, 0, -3)) && self::replace($word, 'ing', '')
                or preg_match("#${v}+#", substr($word, 0, -2)) && self::replace($word, 'ed', '')
            ) { // Note use of && and OR, for precedence reasons
                // If one of above two test successful
                if (! self::replace($word, 'at', 'ate')
                    and ! self::replace($word, 'bl', 'ble')
                    and ! self::replace($word, 'iz', 'ize')
                ) {
                    // Double consonant ending
                    if (self::doubleConsonant($word)
                        and substr($word, -2) !== 'll'
                        and substr($word, -2) !== 'ss'
                        and substr($word, -2) !== 'zz'
                    ) {
                        $word = substr($word, 0, -1);
                    } elseif (self::m($word) === 1 and self::cvc($word)) {
                        $word .= 'e';
                    }
                }
            }
        }

        return $word;
    }

    /**
     * Step 1c
     *
     * @param string $word Word to stem
     */
    private static function step1c($word)
    {
        $v = self::$regex_vowel;

        if (substr($word, -1) === 'y' && preg_match("#${v}+#", substr($word, 0, -1))) {
            self::replace($word, 'y', 'i');
        }

        return $word;
    }

    /**
     * Step 2
     *
     * @param string $word Word to stem
     */
    private static function step2($word)
    {
        switch (substr($word, -2, 1)) {
            case 'a':
                self::replace($word, 'ational', 'ate', 0)
                or self::replace($word, 'tional', 'tion', 0);
                break;

            case 'c':
                self::replace($word, 'enci', 'ence', 0)
                or self::replace($word, 'anci', 'ance', 0);
                break;

            case 'e':
                self::replace($word, 'izer', 'ize', 0);
                break;

            case 'g':
                self::replace($word, 'logi', 'log', 0);
                break;

            case 'l':
                self::replace($word, 'entli', 'ent', 0)
                or self::replace($word, 'ousli', 'ous', 0)
                or self::replace($word, 'alli', 'al', 0)
                or self::replace($word, 'bli', 'ble', 0)
                or self::replace($word, 'eli', 'e', 0);
                break;

            case 'o':
                self::replace($word, 'ization', 'ize', 0)
                or self::replace($word, 'ation', 'ate', 0)
                or self::replace($word, 'ator', 'ate', 0);
                break;

            case 's':
                self::replace($word, 'iveness', 'ive', 0)
                or self::replace($word, 'fulness', 'ful', 0)
                or self::replace($word, 'ousness', 'ous', 0)
                or self::replace($word, 'alism', 'al', 0);
                break;

            case 't':
                self::replace($word, 'biliti', 'ble', 0)
                or self::replace($word, 'aliti', 'al', 0)
                or self::replace($word, 'iviti', 'ive', 0);
                break;
        }

        return $word;
    }

    /**
     * Step 3
     *
     * @param string $word String to stem
     */
    private static function step3($word)
    {
        switch (substr($word, -2, 1)) {
            case 'a':
                self::replace($word, 'ical', 'ic', 0);
                break;

            case 's':
                self::replace($word, 'ness', '', 0);
                break;

            case 't':
                self::replace($word, 'icate', 'ic', 0)
                or self::replace($word, 'iciti', 'ic', 0);
                break;

            case 'u':
                self::replace($word, 'ful', '', 0);
                break;

            case 'v':
                self::replace($word, 'ative', '', 0);
                break;

            case 'z':
                self::replace($word, 'alize', 'al', 0);
                break;
        }

        return $word;
    }

    /**
     * Step 4
     *
     * @param string $word Word to stem
     */
    private static function step4($word)
    {
        $sub = substr($word, -2, 1);
        switch ($sub) {
            case 'a':
            case 'e':
            case 'i':
            case 's':
            case 'u':
            case 'v':
            case 'z':
                self::replace($word, self::END_REPLACERS[$sub], '', 1);
                break;

            case 'c':
                self::replace($word, 'ance', '', 1)
                or self::replace($word, 'ence', '', 1);
                break;

            case 'l':
                self::replace($word, 'able', '', 1)
                or self::replace($word, 'ible', '', 1);
                break;

            case 'n':
                self::replace($word, 'ant', '', 1)
                or self::replace($word, 'ement', '', 1)
                or self::replace($word, 'ment', '', 1)
                or self::replace($word, 'ent', '', 1);
                break;

            case 'o':
                if (substr($word, -4) === 'tion' or substr($word, -4) === 'sion') {
                    self::replace($word, 'ion', '', 1);
                } else {
                    self::replace($word, 'ou', '', 1);
                }
                break;

            case 't':
                self::replace($word, 'ate', '', 1)
                or self::replace($word, 'iti', '', 1);
                break;
        }

        return $word;
    }


    /**
     * Step 5
     *
     * @param string $word Word to stem
     */
    private static function step5($word)
    {
        // Part a
        if (substr($word, -1) === 'e') {
            if (self::m(substr($word, 0, -1)) > 1) {
                self::replace($word, 'e', '');
            } elseif (self::m(substr($word, 0, -1)) === 1) {
                if (! self::cvc(substr($word, 0, -1))) {
                    self::replace($word, 'e', '');
                }
            }
        }

        // Part b
        if (self::m($word) > 1 and self::doubleConsonant($word) and substr($word, -1) === 'l') {
            $word = substr($word, 0, -1);
        }

        return $word;
    }
}
