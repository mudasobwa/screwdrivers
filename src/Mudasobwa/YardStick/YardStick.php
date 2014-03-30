<?php

namespace Mudasobwa\YardStick;

if (file_exists(__DIR__.'/../../../vendor/autoload.php')) {
  require_once 'vendor/autoload.php';
} else {
  die('You MUST run `composer.phar install` prior to use this library.');
}

class YardStickException extends \Exception { }

class YardStick {
  
  private $measures = [];
  
  public function __construct() {
    date_default_timezone_set('Europe/Moscow');
  }
          
  public function milestone($tag = null) {
    $mt = \microtime(true);
    $label = \preg_replace('/\./', '-', "{$mt}");
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
    $result['load'] = $m2['load'][0];
    $result['peak'] = max($m1['peak'], $m2['peak']);
    
    return $result;
  }
  
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
    echo "==== Results for tag=[{$tag}]\n";
    echo "==   Time:      ⇒ {$s_t}\n";
    echo "==   Memory:    ⇒ {$s_m}KB\n";
    echo "==   Peak (1m): ⇒ {$s_p}KB\n";
    echo "==   Load:      ⇒ {$m['load'][0]}\n";
//      echo "== Usage:    ⇒ {$m['usage']}\n\n";
  }
  
  protected function print_diff($tag1, $tag2) {
    $d = $this->diff($tag1, $tag2);
    $d_t = number_format($d['time'], 6);
    $d_m = number_format($d['memory'], 1);
    echo "==== Diff for tags=[{$tag1}, {$tag2}]\n";
    echo "==   Time:      ⇒ {$d_t} sec\n";
    echo "==   Memory:    ⇒ {$d_m} KB\n";
  }
  
  public function report($tags = null) {
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

    if (sizeof($keys) <= 0) {
      throw new YardStickException("No milestones are ready for chosen tags.");
    }

    reset($keys);
    $this->print_values($curr = current($keys));
    while ($nxt = next($keys)) {
      $this->print_diff($curr, $nxt);
      $curr = $nxt;
    }
  }
}

$ys = new YardStick();
$ys->milestone('10');
$r = 0;
for($i=0; $i<10000; $i++) {
  $r += $i^2;
}
$ys->milestone('20');
sleep(1);
$ys->milestone('11');

$ys->report('1\d+');

//sleep(1);
//$ys->milestone();
