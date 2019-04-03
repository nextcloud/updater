Feature: CLI updater - stable13 base

  Scenario: Update is available - 13.0.0 beta 1 to 13.0.0 beta 2
    Given the current installed version is 13.0.0beta1
    And there is an update to prerelease version "13.0.0beta2" available
    And the version number is decreased in the config.php to enforce upgrade
    When the CLI updater is run successfully
    And the output should contain "Update successful"
    Then the installed version should be 13.0
    And maintenance mode should be off
    And upgrade is not required

  Scenario: Update is available - 13.0.0 to 13.0.1
    Given the current installed version is 13.0.0
    And there is an update to version 13.0.1 available
    When the CLI updater is run successfully
    And the output should contain "Update successful"
    Then the installed version should be 13.0.1
    And maintenance mode should be off
    And upgrade is not required

  Scenario: Update is available - 13.0.3 to 13.0.4
    Given the current installed version is 13.0.3
    And there is an update to version 13.0.4 available
    When the CLI updater is run successfully
    And the output should contain "Update successful"
    Then the installed version should be 13.0.4
    And maintenance mode should be off
    And upgrade is not required

  Scenario: Update is available - 13.0.1 to stable14 daily
    Given the current installed version is 13.0.1
    And PHP is at least in version 7.0
    And the current channel is "daily"
    And there is an update to daily version of stable14 available
    When the CLI updater is run successfully
    And the output should contain "Update successful"
    Then the installed version should be 14.0
    And maintenance mode should be off
    And upgrade is not required

  Scenario: Update is available - 13.0.1 to stable14 daily
    Given the current installed version is 13.0.1
    And PHP is at least in version 7.0
    And the current channel is "daily"
    And there is an update to daily version of stable14 available
    When the CLI updater is run successfully
    And the output should contain "Update successful"
    Then the installed version should be 14.0
    And maintenance mode should be off
    And upgrade is not required
