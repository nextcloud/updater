Feature: CLI updater - stable11 base

  Scenario: Update is available - 11.0.3 to 11.0.4
    Given the current installed version is 11.0.3
    And there is an update to version 11.0.4 available
    When the CLI updater is run successfully
    And the output should contain "Update successful"
    Then the installed version should be 11.0.4
    And maintenance mode should be off
    And upgrade is not required

  Scenario: Update is available - 11.0.3 to 12.0.1
    Given the current installed version is 11.0.3
    And there is an update to version 12.0.1 available
    When the CLI updater is run successfully
    And the output should contain "Update successful"
    Then the installed version should be 12.0.1
    And maintenance mode should be off
    And upgrade is not required

  Scenario: Update is available - 11.0.4 to 12.0.1
    Given the current installed version is 11.0.4
    And there is an update to version 12.0.1 available
    When the CLI updater is run successfully
    And the output should contain "Update successful"
    Then the installed version should be 12.0.1
    And maintenance mode should be off
    And upgrade is not required
