Feature: Measurements are to be done properly

  Scenario Outline: Benchmarking simple actions
    Given the input string is <input>
    And the yardsticking is milestoned
    When input string is grepped for symbol "l" 10000 times
    And the yardsticking is milestoned
    And input string is grepped for symbol "o" 100000 times
    And the yardsticking is milestoned
    Then the printout should be correctly <output>

    Examples:
        | input                          | output                             |
        | "Hello, world!"                | "Hello, world!"                    |
