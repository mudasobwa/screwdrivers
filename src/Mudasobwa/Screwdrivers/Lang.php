<?php

/** 
 * Benchmarking helpers. This file is part of the Mudasobwa\Screwdrivers.
 * (c) Alexei Matyushkin <am@mudasobwa.ru>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Mudasobwa\Screwdrivers;

if (file_exists(__DIR__.'/../../../vendor/autoload.php')) {
  require_once 'vendor/autoload.php';
} else {
  die('You MUST run `composer.phar install` prior to use this library.');
}

require_once 'DamerauLevenshtein.php';

/** Common exception class for *YardStick*. */
class LangException extends \Exception { }

/**
 * *Lang* is a class, handling some i18n stuff, like determining the language
 *   of a string.
 * 
 * String language determination may be done in the following ways:
 * * letter (ans possibly di-/tri-graphs) frequency;
 * * word frequency;
 * * rare letters appearance (such as `ñ` for spanish);
 * * rare punctuation appearance (such as `¿¡` for spanish).
 * 
 * Currently the algorithm is:
 * * downcase input;
 * * substitute all the known combining diacriticals with `LATIN1` analogues;
 * * remove all the _words_ containing unknown combining diacriticals;
 * * 
 * 
 * * Words frequency lists: http://en.wiktionary.org/wiki/Wiktionary:Frequency_lists
 */
class Lang {
  /** @var array lists of most frequent words in supported dictionaries */
  private $words = array(
      'en' => array('the', 'be', 'to', 'of', 'and', 'a', 'in', 
          'that', 'have', 'i', 'it', 'for', 'not', 'on', 'with', 'he', 
          'as', 'you', 'do', 'at', 'is'),
      'de' => array('der', 'die', 'und', 'in', 'den', 'von', 
          'zu', 'das', 'mit', 'sich', 'des', 'auf', 'für', 'ist', 'im', 
          'dem', 'nicht', 'ein', 'fuer', 'eine', 'ich'),
      'es' => array('que', 'de', 'no', 'a', 'la', 'el', 'es', 'ser',
          'y', 'en', 'lo', 'un', 'por', 'qué', 'me', 'una', 'te',
          'los', 'se', 'con', 'para'),
      'ru' => array('и', 'в', 'не', 'он', 'на', 'я', 'что', 'тот',
          'был', 'с', 'а', 'весь', 'это', 'как', 'она', 'по', 'но',
          'они', 'к', 'у', 'ты')
  );
  /** @var array lists of most frequent digraphs in supported dictionaries */
  private $digraphs = array(
      'en' => array('th', 'he', 'an', 'in', 'er', 'on', 're',
          'ed', 'nd', 'ha', 'at', 'en', 'es', 'of', 'nt', 'ea', 'ti', 
          'to', 'io', 'le', 'is', 'ou', 'ar', 'as', 'de', 'rt', 've')
  );
  /** @var array lists of most frequent trigraphs in supported dictionaries */
  private $trigraphs = array(
      'en' => array(
          'the', 'and', 'tha', 'ent', 'ion', 'tio', 'for', 'nde', 
          'has', 'nce', 'tis', 'oft', 'men')
  );
  /**
   * Credits to:
   * * http://en.wikipedia.org/wiki/Letter_frequency for latin alphabets
   * * http://www.sttmedia.com/characterfrequency-russian for russian
   * * http://www.letterfrequency.org/ for possible others non-widely used
   * @var array of letters frequency for supported languages
   */
  private static $letters = array(
      'en' => array('e' => 12.70, 't' => 9.05, 'a' => 8.16, 'o' => 7.50, 
          'i' => 6.96, 'n' => 6.74, 's' => 6.32, 'h' => 6.09, 
          'r' => 5.98, 'd' => 4.25, 'l' => 4.02, 'c' => 2.78, 
          'u' => 2.75, 'm' => 2.40, 'w' => 2.36, 'f' => 2.22, 
          'g' => 2.01, 'y' => 1.97, 'p' => 1.92, 'b' => 1.49, 
          'v' => 0.97, 'k' => 0.77, 'j' => 0.15, 'x' => 0.15, 
          'q' => 0.09, 'z' => 0.07),
      'de' => array('e' => 17.39, 'n' => 9.77, 'i' => 7.55, 's' => 7.27,
          'r' => 7.00, 'a' => 6.51, 't' => 6.15, 'd' => 5.07,
          'h' => 4.75, 'u' => 4.34, 'l' => 3.43, 'g' => 3.00,
          'c' => 2.73, 'o' => 2.59, 'm' => 2.53, 'w' => 1.92,
          'b' => 1.88, 'f' => 1.65, 'k' => 1.41, 'z' => 1.13,
          'ü' => 0.99, 'v' => 0.84, 'p' => 0.67, 'ö' => 0.57,
          'ä' => 0.44, 'ß' => 0.30, 'j' => 0.26, 'y' => 0.03,
          'x' => 0.03, 'q' => 0.01),
      'es' => array('e' => 13.68, 'a' => 12.52, 'o' => 8.68, 's' => 7.97,
          'r' => 6.87, 'n' => 6.71, 'i' => 6.24, 'd' => 5.86,
          'l' => 4.96, 't' => 4.63, 'c' => 4.13, 'u' => 3.92,
          'm' => 3.15, 'p' => 2.51, 'b' => 2.21, 'g' => 1.76,
          'v' => 1.13, 'y' => 1.00, 'q' => 0.87, 'ó' => 0.82,
          'í' => 0.72, 'h' => 0.70, 'f' => 0.69, 'z' => 0.51,
          'á' => 0.50, 'j' => 0.44, 'é' => 0.43, 'ñ' => 0.31,
          'x' => 0.21, 'ú' => 0.16, 'w' => 0.01, 'ü' => 0.01,
          'k' => 0.0),
      'ru' => array('о' => 11.07, 'е' => 8.50, 'а' => 7.50, 'и' => 7.09,
          'н' => 6.70, 'т' => 5.97, 'с' => 4.97, 'л' => 4.96,
          'в' => 4.33, 'р' => 4.33, 'к' => 3.30, 'м' => 3.10,
          'д' => 3.09, 'п' => 2.47, 'ы' => 2.36, 'у' => 2.22,
          'б' => 2.01, 'я' => 1.96, 'ь' => 1.84, 'г' => 1.72,
          'з' => 1.48, 'ч' => 1.40, 'й' => 1.21, 'ж' => 1.01,
          'х' => 0.95, 'ш' => 0.72, 'ю' => 0.47, 'ц' => 0.39,
          'э' => 0.36, 'щ' => 0.30, 'ф' => 0.21, 'ё' => 0.20,
          'ъ' => 0.02)
  );
  
  /**
   * '0300' ⇒ 'à', '0301' ⇒ 'á', '0302' ⇒ 'â', '0303' ⇒ 'ã', '0304' ⇒ 'ā', 
   * '0305' ⇒ 'a̅', '0306' ⇒ 'ă', '0307' ⇒ 'ȧ', '0308' ⇒ 'ä', '0309' ⇒ 'ả', 
   * '030A' ⇒ 'å', '030B' ⇒ 'a̋', '030C' ⇒ 'ǎ', '030D' ⇒ 'a̍', '030E' ⇒ 'a̎', 
   * '030F' ⇒ 'ȁ', '0310' ⇒ 'a̐', '0311' ⇒ 'ȃ', '0312' ⇒ 'a̒', '0313' ⇒ 'a̓', 
   * '0314' ⇒ 'a̔', '0315' ⇒ 'a̕', '0316' ⇒ 'a̖', '0317' ⇒ 'a̗', '0318' ⇒ 'a̘', 
   * '0319' ⇒ 'a̙', '031A' ⇒ 'a̚', '031B' ⇒ 'a̛', '031C' ⇒ 'a̜', '031D' ⇒ 'a̝', 
   * '031E' ⇒ 'a̞', '031F' ⇒ 'a̟', '0320' ⇒ 'a̠', '0321' ⇒ 'a̡', '0322' ⇒ 'a̢', 
   * '0323' ⇒ 'ạ', '0324' ⇒ 'a̤', '0325' ⇒ 'ḁ', '0326' ⇒ 'a̦', '0327' ⇒ 'a̧', 
   * '0328' ⇒ 'ą', '0329' ⇒ 'a̩', '032A' ⇒ 'a̪', '032B' ⇒ 'a̫', '032C' ⇒ 'a̬', 
   * '032D' ⇒ 'a̭', '032E' ⇒ 'a̮', '032F' ⇒ 'a̯', '0330' ⇒ 'a̰', '0331' ⇒ 'a̱', 
   * '0332' ⇒ 'a̲', '0333' ⇒ 'a̳', '0334' ⇒ 'a̴', '0335' ⇒ 'a̵', '0336' ⇒ 'a̶', 
   * '0337' ⇒ 'a̷', '0338' ⇒ 'a̸', '0339' ⇒ 'a̹', '033A' ⇒ 'a̺', '033B' ⇒ 'a̻', 
   * '033C' ⇒ 'a̼', '033D' ⇒ 'a̽', '033E' ⇒ 'a̾', '033F' ⇒ 'a̿', , '0340' ⇒ 'à', 
   * '0341' ⇒ 'á', '0342' ⇒ 'a͂', '0343' ⇒ 'a̓', '0344' ⇒ 'ä́', '0345' ⇒ 'aͅ', 
   * '0346' ⇒ 'a͆', '0347' ⇒ 'a͇', '0348' ⇒ 'a͈', '0349' ⇒ 'a͉', '034A' ⇒ 'a͊', 
   * '034B' ⇒ 'a͋', '034C' ⇒ 'a͌', '034D' ⇒ 'a͍', '034E' ⇒ 'a͎', '034F' ⇒ 'a͏', 
   * '0350' ⇒ 'a͐', '0351' ⇒ 'a͑', '0352' ⇒ 'a͒', '0353' ⇒ 'a͓', '0354' ⇒ 'a͔', 
   * '0355' ⇒ 'a͕', '0356' ⇒ 'a͖', '0357' ⇒ 'a͗', '0358' ⇒ 'a͘', '0359' ⇒ 'a͙', 
   * '035A' ⇒ 'a͚', '035B' ⇒ 'a͛', '035C' ⇒ 'a͜', '035D' ⇒ 'a͝', '035E' ⇒ 'a͞', 
   * '035F' ⇒ 'a͟', '0360' ⇒ 'a͠', '0361' ⇒ 'a͡', '0362' ⇒ 'a͢', '0363' ⇒ 'aͣ', 
   * '0364' ⇒ 'aͤ', '0365' ⇒ 'aͥ', '0366' ⇒ 'aͦ', '0367' ⇒ 'aͧ', '0368' ⇒ 'aͨ', 
   * '0369' ⇒ 'aͩ', '036A' ⇒ 'aͪ', '036B' ⇒ 'aͫ', '036C' ⇒ 'aͬ', '036D' ⇒ 'aͭ', 
   * '036E' ⇒ 'aͮ', '036F' ⇒ 'aͯ'
   * 
   * ÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèéêëìíîïðñòóôõöøùúûüýþÿ
   * 
   * @var array The map for _permitted_ diacritics within the languages of
   *   choice. All the diacritics, not specified here, will be simply cut off
   *   from the string before language determination.
   */
  private static $diacritics_latin1_map = array(
      'a' => array(
          '\x{0301}|\x{0341}' => 'á',
          '\x{0308}' => 'ä'
          ),
      'o' => array(
          '\x{0301}|\x{0341}' => 'ó',
          '\x{0308}' => 'ö'
          ),
      'u' => array(
          '\x{0301}|\x{0341}' => 'ú',
          '\x{0308}' => 'ü'
          ),
      'i' => array(
          '\x{0301}|\x{0341}' => 'í'
          ),
      'e' => array(
          '\x{0301}|\x{0341}' => 'é'
          )
  );
  /** @var array internal storage for letters appearing in one and only supported language */
  private static $peculiar_letters = null;

  /** 
   * Getter for array of letters used in the language specified.
   * 
   * @param string $lang the language to retrieve the letters for
   * @return array all the letters used in the language specified as param
   */
  private static function getLetters($lang) {
    return \array_keys(self::$letters[$lang]);
  }
  /** 
   * Getter for string of all letters used in the language specified.
   * 
   * @param string $lang the language to retrieve the letters for
   * @return string all the letters used in the language specified as param,
   *   in order of usage frequency for that particular language
   */
  private static function getLettersAsString($lang, $input = null) {
    $result = \join('', self::getLetters($lang));
    if (is_null($input)) { return $result; };

    $result = \preg_replace("/[^{$input}]+/um", '', $result);
    $input_len = mb_strlen($input, 'UTF-8');
    $sampl_len = mb_strlen($result, 'UTF-8');
    if ($input_len < $sampl_len) {
      throw new \Exception("Internal code error: symbols not stripped.");
    }
    return $input_len > $sampl_len ? $result . \str_repeat('0', $input_len - $sampl_len) :
          ($input_len < $sampl_len ? \mb_substr($result, 0, $input_len, 'UTF-8') : $result);
  }
  /**
   * Getter for letters appearing in one and only supported language (lazy.)
   * 
   * @see self::$peculiar_letters
   * @return array the letters appearing in one and only supported language */
  private static function getPeculiarLetters() {
    if (\is_null(self::$peculiar_letters)) {
      self::$peculiar_letters = array(
        'en' => \array_diff(self::getLetters('en'), 
            self::getLetters('de'), self::getLetters('es'), self::getLetters('ru')),
        'de' => \array_diff(self::getLetters('de'), 
            self::getLetters('en'), self::getLetters('es'), self::getLetters('ru')),
        'es' => \array_diff(self::getLetters('es'), 
            self::getLetters('de'), self::getLetters('en'), self::getLetters('ru')),
        'ru' => \array_diff(self::getLetters('ru'), 
            self::getLetters('de'), self::getLetters('es'), self::getLetters('en'))
      );
    }
    return self::$peculiar_letters;
  }
  
  /**
   * Getter for all the peculiar letters as one string. 
   * 
   * Whether the `$lang` parameter is specified, returnes letters for 
   *   the chosen language only; returns all of them otherwise.
   * 
   * @param string $lang the language to retrieve peculiars for
   * @return string the letters as one string
   */
  private static function getPeculiarLettersAsString($lang = null) {
    $pls = self::getPeculiarLetters();
    if (\is_null($lang)) {
      $res = [];
      foreach (\array_values($pls) as $v) {
        $res = \array_merge_recursive($res, $v);
      }
    } else {
      $res = $pls[$lang];
    }
    return \join('', $res);
  }
  
  private $input;
  private $use_single_byte;
  private $input_letters;
  private $measures;
  
  /**
   * Default constructor.
   * 
   * @param string $s the string to operate on
   * @param bool $use_single_byte use fast one-byte operations for 
   *   `similar_text` and `levenshtein`
   */
  public function __construct($s, $use_single_byte = true) {
    $this->input = $s;
    $this->use_single_byte = $use_single_byte;
    $this->measures = array();
    $this->measures['suggestions'] = array();
    
    $this->gageAll();
  }

  /** 
   * Convert the string to lowercase (multibyte.)
   * 
   * @param string $s string to convert
   * @return string downcased string
   */
  private static function doCase($s) {
    $s = \mb_strtolower($s, 'UTF-8');
    return $s;
  }
  
  /**
   * Get rid of combining diacritics. Substitutes with `LATIN1` those
   *   symbols which are known to be used in at least on of supported
   *   languages; removes the words containing unknown diacritics,
   *   silently claiming them to be names/toponyms.
   * 
   * @param string $s the string to “correct” diacritics in
   * @return string “corrected” string
   */
  private static function doDiacritics($s) {
    // Deal with known diacritics first
    foreach (self::$diacritics_latin1_map as $ltr => $subst) {
      foreach ($subst as $re => $val) {
        $s = \preg_replace("/{$ltr}({$re})/um", $val, $s);
      }
    }
    // Drop occasional names, toponyms etc, containing combining diacriticals.
    $s = \preg_replace('/\S*\pM+\S*/u', '', $s);
    
    // Drop occasional names, toponyms etc, containing LATIN1 diacriticals.
    $peculiars_re = self::getPeculiarLettersAsString();
    $unknown_diacriticals = \preg_replace(
            "/[{$peculiars_re}]/um", '', 'ßàáâãäåæçèéêëìíîïðñòóôõöøùúûüýþÿ');
    $s = \preg_replace("/\S*[{$unknown_diacriticals}]\S*/um", '', $s);
    
    return $s;
  }

  /**
   * Get rid of all the non-letters (including spaces.)
   * 
   * @param string $s the string to remove all non-letters in
   * @return string the string containing the letters only 
   */
  private static function doNonLetters($s) {
    $s = \preg_replace('/\P{L}+/um', '', $s);
    return $s;
  }

  /**
   * @todo FIXME current algorithm is too naïve
   * 
   * Helper to get the most resembling language for the current algorithm.
   * 
   * @param array &$arr the array of languages suggestions
   * @param bool $reversed specifies how to sort array
   * @return array the most resembling language for the current algorithm
   *   (with confidence)
   */
  private static function getTopLangConfidence(&$arr, $reversed = true) {
    $reversed ? \arsort($arr) : \asort($arr);
    $top_lang = current(array_keys($arr));
    reset($arr);
    if ($reversed) {
      $dividend = current($arr);
      $divisor = next($arr);
    } else {
      $divisor = current($arr);
      $dividend = next($arr);
    }
    if ($dividend === $divisor) { 
      $top_lang = 'default'; 
      $confidence = 0;
    } else {
      $confidence = ($divisor ? $dividend / $divisor : $dividend);
    }
    return array('language' => $top_lang, 'confidence' => $confidence);
  }
  /**
   * Counts all the peculiar letters in the input string.
   * This function must be called *before* `doCleanup`.
   * 
   * @return void
   */
  protected function gagePeculiars() {
    if (! \key_exists('peculiars', $this->measures['suggestions'])) {
      $peculiars = array();
      foreach (\array_keys(self::$letters) as $lang) {
        if (!\key_exists($lang, $this->measures)) {
          $this->measures[$lang] = array();
        }
        $peculiars_re = self::getPeculiarLettersAsString($lang);
        $peculiars[$lang] = 
              $this->measures[$lang]['peculiars'] = empty($peculiars_re) ? 
                0 : \preg_match_all("/[{$peculiars_re}]/um", $this->input);
      }
      $this->measures['suggestions']['peculiars'] = 
              $this->getTopLangConfidence($peculiars, true);
    }
    return $this->measures['suggestions']['peculiars'];
  }
  
  /**
   * Counts all the letters frequencies in the input string.
   * This function must be called *after* `doCleanup`.
   * 
   * @return string the string containing all the letters used in the input
   *   ordered by frequency
   */
  protected function gageFrequencies() {
    if (is_null($this->input_letters)) {
      $letters = \preg_split('/(?<!^)(?!$)/um',
              self::doNonLetters(self::doDiacritics(self::doCase($this->input))));
      $letter_count = sizeof($letters);
      $this->measures['frequencies'] = array();
      foreach($letters as $l) {
        if (!key_exists($l, $this->measures['frequencies'])) {
          $this->measures['frequencies'][$l] = 0;
        }
        $this->measures['frequencies'][$l] += 1.0 / $letter_count;
      }
      \arsort($this->measures['frequencies']);
      $this->input_letters = \join('', array_keys($this->measures['frequencies']));
    }
    return $this->input_letters;
  }
  
  /**
   * Convert an UTF-8 encoded string to a single-byte string suitable for
   * functions such as levenshtein.
   *
   * The function simply uses (and updates) a tailored dynamic encoding
   * (in/out map parameter) where non-ascii characters are remapped to
   * the range [128-255] in order of appearance.
   *
   * Thus it supports up to 128 different multibyte code points max over
   * the whole set of strings sharing this encoding.
   *
   * Credits: http://www.php.net/manual/en/function.levenshtein.php#113702
   * 
   * @todo FIXME Weird error 
   *   `PHP Fatal error:  Using $this when not in object context`
   *   if I have `$this->use_single_byte` uncommented. WTF?
   */
  
  private function utf8ToExtendedAscii($str, &$map) {
    // FIXME
    if (!$this->use_single_byte) {
      return $str; // try to deal with it as is
    }
    
    // find all multibyte characters (cf. utf-8 encoding specs)
    $matches = array();
    if (!preg_match_all('/[\xC0-\xF7][\x80-\xBF]+/', $str, $matches)) {
      return $str; // plain ascii string
    }
 
    // update the encoding map with the characters not already met
    foreach ($matches[0] as $mbc) {
      if (!isset($map[$mbc])) {
        if (count($map) >= 127) {
          throw new \OutOfBoundsException("There are too much different unicode symbols in input. Consider not to use single byte methods.");
        }
        $map[$mbc] = chr(128 + count($map));
      }
    }
    
    // finally remap non-ascii characters
    return strtr($str, $map);
  }

  /**
   * Didactic example showing the usage of the previous conversion function but,
   * for better performance, in a real application with a single input string
   * matched against many strings from a database, you will probably want to
   * pre-encode the input only once.
   */
  private function levenshteinUtf8($s1, $s2) {
    $charMap = array();
    $s1 = $this->utf8ToExtendedAscii($s1, $charMap);
    $s2 = $this->utf8ToExtendedAscii($s2, $charMap);

    return \levenshtein($s1, $s2);
  }
  
  /**
   * Calculates the Levenshtein distance between frequencies of letters
   *   in input string against supported languages.
   * 
   * @return string the language for which the distance is minimal.
   */
  protected function gageLevenshtein() {
    if (! \key_exists('levenshtein', $this->measures['suggestions'])) {
      $distances = array();
      foreach (\array_keys(self::$letters) as $lang) {
        $freqs = $this->gageFrequencies();
        $this->measures[$lang]['levenshtein'] = $this->levenshteinUtf8(
                $freqs, self::getLettersAsString($lang, $freqs)
        );
        $distances[$lang] = $this->measures[$lang]['levenshtein'];
      }
      $this->measures['suggestions']['levenshtein'] = 
              $this->getTopLangConfidence($distances, false);
    }
    return $this->measures['suggestions']['levenshtein']['language'];
  }

  /**
   * Internal calculation of mudasobwa distance.
   */
  private function mudasobwaUtf8($s1, $s2, $weights) {
    $result = 0;

    foreach($weights as $l => $w) {
      if (!($pos1 = mb_strpos($s1, $l, 0, 'UTF-8'))) {
        $pos1 = 0;
      }
      if (!($pos2 = mb_strpos($s2, $l, 0, 'UTF-8'))) {
        $pos2 = 0;
      }
      if ($pos1 + $pos2 > 0) {
        $result += (\abs($pos1 - $pos2)) * $w / ($pos1 + $pos2);
      }
    }
    return $result;
  }

  /**
   * Calculates the Mudasobwa distance (with weights) between frequencies of letters
   *   in input string against supported languages.
   * 
   * @return string the language for which the distance is minimal.
   */
  protected function gageMudasobwa() {
    if (! \key_exists('mudasobwa', $this->measures['suggestions'])) {
      $distances = array();
      $distances_supplemental = array();
      foreach (\array_keys(self::$letters) as $lang) {
        $freqs = $this->gageFrequencies();
        $this->measures[$lang]['mudasobwa'] = $this->mudasobwaUtf8(
                $freqs,
                self::getLettersAsString($lang, $freqs),
                $this->measures['frequencies'] // self::$letters[$lang]
        );
        $distances[$lang] = $this->measures[$lang]['mudasobwa'];
        $this->measures[$lang]['mudasobwa-supplemental'] = $this->mudasobwaUtf8(
                $freqs,
                self::getLettersAsString($lang, $freqs),
                self::$letters[$lang]
        );
        $this->measures[$lang]['mudasobwa-supplemental'] = 
                ($this->measures[$lang]['mudasobwa-supplemental'] <= 0) ?
                100.0 : $this->measures[$lang]['mudasobwa-supplemental'] / 100.0;
        $distances_supplemental[$lang] = $this->measures[$lang]['mudasobwa-supplemental'];
      }
      $this->measures['suggestions']['mudasobwa'] =
              $this->getTopLangConfidence($distances, false);
      $this->measures['suggestions']['mudasobwa-supplemental'] =
              $this->getTopLangConfidence($distances_supplemental, false);
    }
    return ($this->measures['suggestions']['mudasobwa']['language'] === 'default') ?
            $this->measures['suggestions']['mudasobwa-supplemental']['language'] :
            $this->measures['suggestions']['mudasobwa']['language'];
  }
  
  /** 
   * Plain similarity of two strings.
   * 
   * @param string $s1 the string of input letters in order of appearance frequency
   * @param string $s2 the string of language letters in order of appearance frequency
   * @param int $percent
   * @return the similarity as returned by plaint `similar_text`
   */
  private function similarTextUtf8($s1, $s2, &$percent) {
    $charMap = array();
    $s1 = $this->utf8ToExtendedAscii($s1, $charMap);
    $s2 = $this->utf8ToExtendedAscii($s2, $charMap);

    return \similar_text($s1, $s2, $percent);
  }

  /**
   * Calculates the text similarity between frequencies of letters
   *   in input string against supported languages.
   * 
   * @return string the language for which the distance is minimal.
   */
  protected function gageSimilarity() {
    if (! \key_exists('similarity', $this->measures['suggestions'])) {
      $similarities = array();
      foreach (\array_keys(self::$letters) as $lang) {
        $freqs = $this->gageFrequencies();
        $this->measures[$lang]['similarity'] = $this->similarTextUtf8(
          $freqs, self::getLettersAsString($lang, $freqs), $percent
        );
        $similarities[$lang] = $this->measures[$lang]['similarity'];
      }
      $this->measures['suggestions']['similarity'] = 
              $this->getTopLangConfidence($similarities, true);
    }
    return $this->measures['suggestions']['similarity']['language'];
  }
  
  /**
   * Tries to find the most resembling language.
   * 
   * @return array the array of suggested languages with confidence
   */
  protected function gageMostResemblingLanguage() {
    if (! \key_exists('languages', $this->measures['suggestions'])) {
      $langs = array();
      foreach ($this->measures['suggestions'] as $sugg) {
        if (\is_array($sugg) && \key_exists('language', $sugg)) {
          $langs[] = $sugg['language'];
        }
      }
      $counts = \array_count_values($langs);
      \arsort($counts);
      $this->measures['suggestions']['languages'] = $counts;
    }
    return $this->measures['suggestions']['languages'];
  }
  /** 
   * Measures the Damerau-Levenshtein distance of two words.
   * 
   * @param string $str1
   * @param string $str2
   * @return int the distance between two words according to Damerau-Levenshtein
   */
  private function damerauLevenshteinUtf8($str1, $str2) {
    $d = [];
    
    $charMap = array();
    $str1 = $this->utf8ToExtendedAscii($str1, $charMap);
    $str2 = $this->utf8ToExtendedAscii($str2, $charMap);

    $lenStr1 = strlen($str1); $lenStr2 = strlen($str2);
    if ($lenStr1 == 0) { return $lenStr2; }
    if ($lenStr2 == 0) { return $lenStr1; }

    for ($i = 0; $i <= $lenStr1; $i++) {
      $d[$i] = [];
      $d[$i][0] = $i;
    }

    for ($j = 0; $j <= $lenStr2; $j++) {
      $d[0][$j] = $j;
    }

    for ($i = 1; $i <= $lenStr1; $i++) {
      for ($j = 1; $j <= $lenStr2; $j++) {
        $cost = substr($str1, $i - 1, 1) == substr($str2, $j - 1, 1) ? 0 : 1;

        $d[$i][$j] = min(
                $d[$i - 1][$j] + 1, // deletion
                $d[$i][$j - 1] + 1, // insertion
                $d[$i - 1][$j - 1] + $cost          // substitution
        );

        if (
                $i > 1 &&
                $j > 1 &&
                substr($str1, $i - 1, 1) == substr($str2, $j - 2, 1) &&
                substr($str1, $i - 2, 1) == substr($str2, $j - 1, 1)
        ) {
          $d[$i][$j] = min(
                $d[$i][$j], $d[$i - 2][$j - 2] + $cost          // transposition
          );
        }
      }
    }
    return $d[$lenStr1][$lenStr2];
  }

  /**
   * Measures the Damerau-Levenshtein distance between frequencies of letters
   *   in input string against supported languages.
   * 
   * @return string the most resembling language
   */
  protected function gageDamerauLevenshtein() {
    if (! \key_exists('damerau', $this->measures['suggestions'])) {
      $damerau = array();
      foreach (\array_keys(self::$letters) as $lang) {
        $freqs = $this->gageFrequencies();
        $this->measures[$lang]['damerau'] =
            (\extension_loaded('damerau') && function_exists('damerau_levenshtein')) ?
            \damerau_levenshtein(
                $freqs, self::getLettersAsString($lang, $freqs)
            ) :
            $this->damerauLevenshteinUtf8(
                $freqs, self::getLettersAsString($lang, $freqs)
            );
        $damerau[$lang] = $this->measures[$lang]['damerau'];
      }
      $this->measures['suggestions']['damerau'] = 
              $this->getTopLangConfidence($damerau, false);
    }
    return $this->measures['suggestions']['damerau']['language'];
  }

  /**
   * Measures the Damerau-Levenshtein distance between frequencies of letters
   *   in input string against supported languages *with costs*.
   * 
   * @return string the most resembling language
   */
  protected function gageDamerauLevenshteinExt() {
    if (! \key_exists('damerau-ext', $this->measures['suggestions'])) {
      $damerau = array();
      foreach (\array_keys(self::$letters) as $lang) {
        $freqs = $this->gageFrequencies();
        $dl = new \DamerauLevenshtein(
                $freqs, self::getLettersAsString($lang, $freqs), 1,1,1,10
        );
        $this->measures[$lang]['damerau-ext'] = $dl->getSimilarity();
        $damerau[$lang] = $this->measures[$lang]['damerau-ext'];
      }
      $this->measures['suggestions']['damerau-ext'] = 
              $this->getTopLangConfidence($damerau, false);
    }
    return $this->measures['suggestions']['damerau-ext']['language'];
  }

  /**
   * Calculates all the available values.
   * 
   * @return string the language most likely used in the string given.
   * 
   */
  protected function gageAll() {
    $this->gagePeculiars();
    $this->gageFrequencies();
    $this->gageLevenshtein();
    $this->gageDamerauLevenshtein();
    $this->gageMudasobwa();
//    $this->gageDamerauLevenshteinExt();
    $this->gageSimilarity();
    $this->measures['suggestions']['letters'] = $this->gageFrequencies();

    // Should be the last call (follow all other gages.)
    $this->gageMostResemblingLanguage();
  }
  
  public function printMeasures($detailed = false) {
    print_r($detailed ? $this->measures : $this->measures['suggestions']);
  }
  
  public static function measure($s, $detailed = false) {
    $instance = new Lang($s);
    $instance->printMeasures($detailed);
  }

  /**
   * Suggests language basing on currently made gages.
   * 
   * The result *is not* cached to simplify logic. All gages *are* cached
   *   and finding the max of 4-5 elements array costs nothing. 
   * 
   * @return string the most resembling language
   */
  public function suggestLanguage($use_mudasobwa = true) {
    if ($use_mudasobwa) {
      return $this->measures['suggestions']['mudasobwa']['language'] === 'default' ?
             $this->measures['suggestions']['mudasobwa-supplemental']['language'] :
             $this->measures['suggestions']['mudasobwa']['language'];
    }

    $res = \array_keys($this->measures['suggestions']['languages']);
    $lang = \array_shift($res);
    return (empty($res) || $lang !== 'default') ? $lang : \array_shift($res);
  }

  public static function language($s) {
    $instance = new Lang($s);
    return $instance->suggestLanguage();
  }

}

$s_ru = <<<EOT
— Вы не видали еще, — или: — вы не знакомы с? — говорила Анна Павловна приезжавшим гостям и весьма серьезно подводила их к маленькой старушке в высоких бантах, выплывшей из другой комнаты, как скоро стали приезжать гости, называла их по имени, медленно переводя глаза с гостя на и потом отходила.
Все гости совершали обряд приветствования никому не известной, никому не интересной и не нужной тетушки. Анна Павловна с грустным, торжественным участием следила за их приветствиями, молчаливо одобряя их. каждому говорила в одних и тех же выражениях о его здоровье, о своем здоровье и о здоровье ее величества, которое нынче было, слава Богу, лучше. Все подходившие, из приличия не выказывая поспешности, с чувством облегчения исполненной тяжелой обязанности отходили от старушки, чтоб уж весь вечер ни разу не подойти к ней.
Молодая княгиня Болконская приехала с работой в шитом золотом бархатном мешке. Ее хорошенькая, с чуть черневшимися усиками верхняя губка была коротка по зубам, но тем милее она открывалась и тем еще милее вытягивалась иногда и опускалась на нижнюю. Как это бывает у вполне привлекательных женщин, недостаток ее — короткость губы и полуоткрытый рот — казались ее особенною, собственно ее красотой. Всем было весело смотреть на эту полную здоровья и живости хорошенькую будущую мать, так легко переносившую свое положение. Старикам и скучающим, мрачным молодым людям казалось, что они сами делаются похожи на нее, побыв и поговорив несколько времени с ней. Кто говорил с ней и видел при каждом слове ее светлую улыбочку и блестящие белые зубы, которые виднелись беспрестанно, тот думал, что он особенно нынче любезен. И это думал каждый.
Маленькая княгиня, переваливаясь, маленькими быстрыми шажками обошла стол с рабочею сумочкой на руке и, весело оправляя платье, села на диван, около серебряного самовара, как будто все, что она ни делала, было для нее и для всех ее окружавших.        
EOT;

$s_en = <<<EOT
The task of defining what constitutes a "word" involves determining where one word ends and another word begins—in other words, identifying word boundaries. There are several ways to determine where the word boundaries of spoken language should be placed:
Potential pause: A speaker is told to repeat a given sentence slowly, allowing for pauses. The speaker will tend to insert pauses at the word boundaries. However, this method is not foolproof: the speaker could easily break up polysyllabic words, or fail to separate two or more closely related words.
Indivisibility: A speaker is told to say a sentence out loud, and then is told to say the sentence again with extra words added to it. Thus, I have lived in this village for ten years might become My family and I have lived in this little village for about ten or so years. These extra words will tend to be added in the word boundaries of the original sentence. However, some languages have infixes, which are put inside a word. Similarly, some have separable affixes; in the German sentence "Ich komme gut zu Hause an", the verb ankommen is separated.
Phonetic boundaries: Some languages have particular rules of pronunciation that make it easy to spot where a word boundary should be. For example, in a language that regularly stresses the last syllable of a word, a word boundary is likely to fall after each stressed syllable. Another example can be seen in a language that has vowel harmony (like Turkish): the vowels within a given word share the same quality, so a word boundary is likely to occur whenever the vowel quality changes. Nevertheless, not all languages have such convenient phonetic rules, and even those that do present the occasional exceptions.
Orthographic boundaries: See below.
EOT;

$s_de = <<<EOT
Geschriebene Wörter werden mit Buchstaben, Schriftzeichen oder Symbolen dargestellt und in vielen Sprachen durch Leerzeichen vor dem Wort oder Satzzeichen voneinander abgetrennt. Im klassischen Chinesischen entspricht jedem Zeichen ein Wort, ein Morphem und eine Silbe.
Die in einer Sprache verwendete Schrift ist reine Konvention und entwickelt sich zumeist historisch weiter. Beim Übergang einer Schrift von einer Sprache zu einer anderen, nicht näher verwandten Sprache gibt es Zeichen für Laute, die es in der neuen Sprache nicht gibt, und andererseits Laute, für die es keine Zeichen gibt. Meist wird dann die Schrift angepasst, indem Zeichen nicht weiter verwendet werden, übrige Zeichen für andere Laute verwendet werden, Kombinationen von Zeichen eingeführt oder Zeichen durch diakritische Zeichen modifiziert werden oder zusätzliche Zeichen eingeführt werden. Die Schreibrichtung ist abhängig von den Schreibregeln und beruht darauf, dass sich eine Richtung historisch durchgesetzt hat.
EOT;

$s_es = <<<EOT
La semántica léxica es el estudio de lo que denotan las palabras de una lengua natural. Las palabras pueden o bien denotar entidades físicas del mundo, o bien conceptos. Las unidades de significado en la semántica léxica se denominan unidades léxicas. Las lenguas naturales tienen la capacidad de añadir nuevas unidades léxicas a medida que surgen cambios históricos y nuevas realidades en las comunidades de hablantes que las usan.
La semántica léxica incluye teorías y propuestas de clasificación y análisis del significado de las palabras, las diferencias y similiaridades en la organización del lexicón de los diversos idiomas y la relación entre el significado de las palabras y el significado de las oraciones y la sintaxis.
Una cuestión importante que explora la semántica léxica es si el significado de una unidad léxica queda determinado examinando su posición y relaciones dentro de una red semántica o si por el contrario el significado está localmente contenido en la unidad léxica. Esto conduce a dos enfoques diferentes de la semántica léxica. Otro tópico explorado es la relación de representación entre formas léxicas y conceptos. Finalmente debe señalarse que en semántica léxica resultan importantes la relaciones de sinonimia, antonimia, hiponimia e hiperonomia para analizar las cuestiones anteriores.
EOT;

//echo "RU ⇒ [" . Lang::language($s_ru) . "]\n";
//Lang::measure($s_ru, true);
//echo "EN ⇒ [" . Lang::language($s_en) . "]\n";
//Lang::measure($s_en, true);
//echo "DE ⇒ [" . Lang::language($s_de) . "]\n";
//Lang::measure($s_de, true);
//echo "ES ⇒ [" . Lang::language($s_es) . "]\n";
//Lang::measure($s_es, true);

$s_ru = "Привет, сказал странный человек в синих одеждах.";
$s_en = "I always loved grid view because I could easily display my record.";
$s_de = "Hallo, sagte ein fremder Mann in blauen Gewändern.";
$s_es = "Hola, ha dicho un hombre extraño con túnicas azules.";

echo "RU ⇒ [" . Lang::language($s_ru) . "]\n";
echo "EN ⇒ [" . Lang::language($s_en) . "]\n";
Lang::measure($s_en, true);
echo "DE ⇒ [" . Lang::language($s_de) . "]\n";
echo "ES ⇒ [" . Lang::language($s_es) . "]\n";
// Lang::measure($s_es, true);

/*


//Lang::determine($s_ru);
//Lang::determine($s_en);
//Lang::determine($s_de);
//Lang::determine(iconv('UTF-8', 'US-ASCII//TRANSLIT', $s_es));

Lang::measure($s_en, true);
Lang::measure($s_de, true);
Lang::measure($s_es, true);
 */
