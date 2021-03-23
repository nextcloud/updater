Feature: CLI updater - stable19 base

  Scenario: Update is available - 19.0.0 beta 3 to 19.0.0 beta 4
    Given the current installed version is 19.0.0beta3
    And there is an update to prerelease version "19.0.0beta4" available
    And the version number is decreased in the config.php to enforce upgrade
    When the CLI updater is run successfully
    And the output should contain "Update successful"
    Then the installed version should be 19.0
    And maintenance mode should be off
    And upgrade is not required

  Scenario: Update is available - 19.0.0 to 19.0.1
    Given the current installed version is 19.0.0
    And there is an update to version 19.0.1 available
    When the CLI updater is run successfully
    And the output should contain "Update successful"
    Then the installed version should be 19.0.1
    And maintenance mode should be off
    And upgrade is not required

  Scenario: Update is available - 19.0.1 to 20.0.0
    Given the current installed version is 19.0.1
    And PHP is at least in version 7.2
    And the current channel is "beta"
    And there is an update to version 20.0.0 available
    When the CLI updater is run successfully
    And the output should contain "Update successful"
    Then the installed version should be 20.0.0
    And maintenance mode should be off
    And upgrade is not required

