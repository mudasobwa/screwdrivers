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

/** Common exception class for *YardStick*. */
class YardStickException extends \Exception { }

/**
 * *YardStick* is a class, providing easy access to benchmarking.
 * 
 * It allows the embedded in code becnhmarks, such as:
 * 
 *     …
 *     $ys = new \Mudasobwa\Screwdrivers\YardStick(true);
 *     $ys->milestone('YS1-Start');
 *     $my_obj->perform_long_operation($param1, $param2);
 *     $ys->milestone('YS2');
 *     $my_obj->perform_long_operation($param3, $param4);
 *     $ys->milestone('YS1-Finish');
 *     $ys->report('YS.+'); // report measures for milestones `YS*`
 *
 * Another way is to measure the specific methods (and/or compare them):
 * 
 *     \Mudasobwa\Screwdrivers\YardStick::benchmark(
 *        new FlexibleString('Hello, world!'), 'replace', array('/l/', 'L')
 *     );
 * 
 * Partially inspired by https://github.com/fotuzlab/appgati
 * Credits to http://www.ruby-doc.org/stdlib-2.0/libdoc/benchmark/rdoc/Benchmark.html
 */
class YardStick {
  /** Local storage for all the measures made. */
  private $measures = [];
  
  /** Class-wide marker for whether the measurements are to be made. */
  private static $in_use = false;
  /** Instance-wide marker for whether the measurements are to be made. */
  private $instance_in_use;
  
  /** Default amount of iterations to be made for measurements. */
  const ITERATIONS = 10000;
  
  /** Default constructor. 
   * 
   * @param bool $instance_in_use suppresses measurements if `false`.
   */
  public function __construct($instance_in_use = false) {
    // What if I WANT to rely on system’s settings?!
    date_default_timezone_set('UTC');
    $this->instance_in_use = $instance_in_use;
  }

  /** 
   * Enables measurements globally.
   * 
   * @param bool $in_use turns on all the measurements if `true`.
   */
  public static function engage($in_use = false) {
    self::$in_use = $in_use;
  }
  
  /**
   * Declares milestone. Once called somewhere in the source code,
   * it snapshots the system’s values (time, memore allocated etc.)
   * for further comparisions against other milestones and final report.
   * 
   * @param string $tag name of the milestone; prepended with current
   *  time snapshot in microseconds, that’s why may be used twice for
   *  easy milestone filtering for reports.
   * @throws YardStickException if called at the same microsecond as
   *  previous call (this should never occur, but we decided to defend
   *  against random weird circumstances.)
   */
  public function milestone($tag = null) {
    if (!$this->instance_in_use && !self::$in_use) { return; }
    
    $mt = \microtime(true);
    $label = "{$mt}";
    if ($tag) { $label .= "-{$tag}"; }
    
    if (key_exists($label, $this->measures)) {
      throw new YardStickException("Milestones are set for the same time. Reset milestones.");
    }
    
    $this->measures[$label] = [];
    $this->measures[$label]['time'] = $mt;
    $this->measures[$label]['memory'] = \memory_get_usage();
    $this->measures[$label]['load'] = \sys_getloadavg();
    $this->measures[$label]['peak'] = \memory_get_peak_usage();
    $this->measures[$label]['usage'] = \getrusage();
  }
  
  /**
   * Reports initial system snapshot and measurements for all the requested
   *  milestones.
   * 
   * @param mixed $tags either regular expression to filter the milestones to be 
   *  included in the report or an array of names. E.g. `report('U\d+$')` 
   *  will include in the report only the milestones, tagged starting with `U` 
   *  followed by at least one digit. `U123` will be included in the report, 
   *  while `U0RR` will not. `report(array('U1', 'Z2')` will include tags 
   *  `U1` and `Z2` respectively.
   */
  public function report($tags = null) {
    if (!$this->instance_in_use && !self::$in_use) { return; }
    
    if (is_null($tags)) {
      $keys = array_keys($this->measures);
    } else if (!is_array($tags)) {
      $keys = preg_grep("/-{$tags}$/u", array_keys($this->measures));
    } else {
      $keys = [];
      foreach ($tags as $tag) {
        $keys = array_merge(
                  $keys,
                  preg_grep("/-{$tag}$/u", array_keys($this->measures))
                );
      }
    }

    $this->print_all($keys);
  }

  /** 
   * Reports benchmarking for the method, specified by `$meth` param,
   *  called on instance `$obj` with parameters `$params` an `$iterations`
   *  amount of times.
   * 
   * Will do nothing until class-wide `$in_use` variable is set to `true`.
   * 
   * @param object $obj the object to call method on
   * @param string $meth the method to call
   * @param array $params the array of parameters
   * @param int $iterations the number of iterations
   */
  public static function benchmark($obj, $meth, $params, $iterations = self::ITERATIONS) {
    if (!self::$in_use) { return; }
    
    $refMeth = new \ReflectionMethod(get_class($obj), $meth);
    $ys = new YardStick();
    $ys->milestone();
    for ($i=0; $i<$iterations; $i++) {
      $refMeth->invokeArgs($obj, $params);
    }
    $ys->milestone();
    $ys->report();
  }

  /**
   * Calculates difference between two tags.
   * 
   * @param string $tag1 the exact name of the tag to measure diffs *from*.
   * @param string $tag2 the exact name of the tag to measure diffs *to*.
   * @return array the array containing time, memory etc.
   * @throws YardStickException if there were no measurements for either 
   *   tag specified.
   */
  protected function diff($tag1, $tag2) {
    if (!key_exists($tag1, $this->measures) || !key_exists($tag2, $this->measures)) {
      throw new YardStickException("Wrong tags are given for diff: [{$tag1}, {$tag2}]");
    }
    
    $m1 = $this->measures[$tag1];
    $m2 = $this->measures[$tag2];

    $result = [];
    $result['time'] = $m2['time'] - $m1['time'];
    $result['memory'] = ($m2['memory'] - $m1['memory']) / 1024;
    
    // Fulfil with reasonable values…
    $result['load'] = max($m1['load'][0], $m2['load'][0]);
    $result['peak'] = max($m1['peak'], $m2['peak']);
    
    return $result;
  }
  
  /**
   * Prints system load for the tag specified to `stdout`.
   * 
   * @todo formatter, different output destinations.
   * 
   * @param string $tag the tag to print system load for.
   * @throws YardStickException if there were no measurements for either 
   *   tag specified.
   */
  protected function print_values($tag = null) {
    if (sizeof($this->measures) <= 0) {
      throw new YardStickException("No milestones passed yet.");
    }
    if (is_null($tag)) { // Will return last measurement
      end($this->measures);
      $tag = key($this->measures);
    }
    $m = $this->measures[$tag];
    $s_t = strftime("%D %T", (int)$m['time']);
    $s_m = (int)($m['memory'] / 1024);
    $s_p = (int)($m['peak'] / 1024);
    $tag = preg_replace('/.*?-(.*)/u', '\1', $tag);
    echo "==== Results for tag: [{$tag}]\n";
    echo "--   ⌚ Time     ⇒ {$s_t}\n";
    echo "--   ⌛ Memory:  ⇒ {$s_m}KB\n";
    echo "--   Peak (1m): ⇒ {$s_p}KB\n";
    echo "--   Load:      ⇒ {$m['load'][0]}\n";
//      echo "-- Usage:    ⇒ {$m['usage']}\n\n";
  }

  /** Prints differents between two system loads for tags specified.
   * 
   * @param string $tag1 the exact name of the tag to measure diffs *from*.
   * @param string $tag2 the exact name of the tag to measure diffs *to*.
   */
  protected function print_diff($tag1, $tag2) {
    $d = $this->diff($tag1, $tag2);
    $d_t = number_format($d['time'], 6);
    $d_m = number_format($d['memory'], 1);
    $tag1 = preg_replace('/.*?-(.*)/u', '\1', $tag1);
    $tag2 = preg_replace('/.*?-(.*)/u', '\1', $tag2);
    echo "==== Diff for tags: [{$tag1} :: {$tag2}]\n";
    echo "--   ⌚ Time:    ⇒ {$d_t} sec\n";
    echo "--   ⌛ Memory:  ⇒ {$d_m} KB\n";
  }

  /**
   * Prints initial system state/load and all the diffs between the keys (tags)
   *  requested. May be asked to print all the results (`$keys === null`),
   * 
   * @param array $keys the tags to print the diffs for.
   * @throws YardStickException if an array specified as param has zero size.
   */
  protected function print_all($keys) {
    if (sizeof($keys) <= 0) {
      throw new YardStickException("No milestones are ready for chosen tags.");
    }

    reset($keys);
    $this->print_values($curr = current($keys));
    while ($nxt = next($keys)) {
      $this->print_diff($curr, $nxt);
      $curr = $nxt;
    }
    if (sizeof($keys) > 2) {
      echo "——————————————————————————————————————\n";
      reset($keys);
      $this->print_diff(current($keys), $curr);
    }
  }
  
}


