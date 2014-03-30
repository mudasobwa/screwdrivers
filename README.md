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

It allows the embedded in code becnhmarks, such as:

```php
…
$ys = new \Mudasobwa\Screwdrivers\YardStick(true);
$ys->milestone('YS1-Start');
$my_obj->perform_long_operation($param1, $param2);
$ys->milestone('YS2');
$my_obj->perform_long_operation($param3, $param4);
$ys->milestone('YS1-Finish');
$ys->report('YS.+'); // report measures for milestones `YS*`
```

Another way is to measure the specific methods (and/or compare them):

```php
\Mudasobwa\Screwdrivers\YardStick::benchmark(
   new FlexibleString('Hello, world!'), 'replace', array('/l/', 'L')
);
```