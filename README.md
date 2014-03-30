To ease the PHP development

### Installation

    $ vim composer.json

```json
{
  "require": {
    "mudasobwa/screwdrivers": "0.1.*"
  }
}
```

    $ curl http://getcomposer.org/installer | php
    $ php composer.phar install --prefer-source

### YardStick

*YardStick* is a class, providing easy access to benchmarking.

It allows the embedded-in-code becnhmarks, such as:

```php
…
$ys = new \Mudasobwa\Screwdrivers\YardStick(true);
$ys->milestone('YS1#Start');
$my_obj->perform_long_operation($param1, $param2);
$ys->milestone('YS2');
$my_obj->perform_long_operation($param3, $param4);
$ys->milestone('YS1#Finish');
$ys->report('YS.+'); // report measures for milestones `YS*`
```

The output will be looking like:

    ==== Results for tag: [1396189882.6664-YS1#Start]
    --   ⌚ Time     ⇒ 03/30/14 14:31:22
    --   ⌛ Memory:  ⇒ 6915KB
    --   Peak (1m): ⇒ 7075KB
    --   Load:      ⇒ 0.82
    ==== Diff for tags: [1396189882.6664 :: 1396189882.6989]
    --   ⌚ Time:    ⇒ 0.032443 sec
    --   ⌛ Memory:  ⇒ 7.0 KB
    ==== Diff for tags: [1396189882.6989 :: 1396189882.98]
    --   ⌚ Time:    ⇒ 0.281102 sec
    --   ⌛ Memory:  ⇒ 5.9 KB
    ——————————————————————————————————————
    ==== Diff for tags: [1396189882.6664 :: 1396189882.98]
    --   ⌚ Time:    ⇒ 0.313545 sec
    --   ⌛ Memory:  ⇒ 12.9 KB


Another way is to measure the specific methods (and/or compare them):

```php
\Mudasobwa\Screwdrivers\YardStick::benchmark(
   new FlexibleString('Hello, world!'), 'replace', array('/l/', 'L')
);
```