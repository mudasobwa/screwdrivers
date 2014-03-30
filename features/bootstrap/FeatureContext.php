<?php

use Behat\Behat\Context\ClosuredContextInterface,
    Behat\Behat\Context\TranslatedContextInterface,
    Behat\Behat\Context\BehatContext,
    Behat\Behat\Exception\PendingException;
use Behat\Gherkin\Node\PyStringNode,
    Behat\Gherkin\Node\TableNode;

//
// Require 3rd-party libraries here:
//
//   require_once 'PHPUnit/Autoload.php';
//   require_once 'PHPUnit/Framework/Assert/Functions.php';
//

/**
 * Features context.
 */
class FeatureContext extends BehatContext {

  private $input, $pr;
  private $ys;

  /**
   * Initializes context.
   * Every scenario gets it's own context object.
   *
   * @param array $parameters context parameters (set them up through behat.yml)
   */
  public function __construct(array $parameters) {
    $this->ys = new \Mudasobwa\Screwdrivers\YardStick(true);
  }

  /**
   * @Given /^the input string is "([^"]*)"$/
   */
  public function theInputStringIs($input) {
    $this->input = $input;
  }

  /**
   * @When /^input string is grepped for symbol "([^"]*)" (\d+) times$/
   */
  public function inputStringIsGreppedForSymbolTimes($s, $times) {
    for ($i = 0; $i < $times; $i++) {
      $pr = \preg_replace("/{$s}/u", '×', $this->input);
    }
  }

  /**
   * @Given /^the yardsticking is milestoned$/
   */
  public function theYardstickingIsMilestoned() {
    $this->ys->milestone();
  }

  /**
   * @Then /^the printout should be correctly "([^"]*)"$/
   */
  public function thePrintoutShouldBeCorrectly($arg1) {
    $this->ys->report();
  }

}
