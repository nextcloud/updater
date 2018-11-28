Feature: CLI updater - stable14 base

  Scenario: Update is available - 14.0.0 beta 1 to 14.0.0 beta 2
    Given the current installed version is 14.0.0beta1
    And there is an update to prerelease version "14.0.0beta2" available
    And the version number is decreased in the config.php to enforce upgrade
    When the CLI updater is run successfully
    And the output should contain "Update successful"
    Then the installed version should be 14.0
    And maintenance mode should be off
    And upgrade is not required

  Scenario: Update is available - 14.0.0 to 14.0.1
    Given the current installed version is 14.0.0
    And there is an update to version 14.0.1 available
    When the CLI updater is run successfully
    And the output should contain "Update successful"
    Then the installed version should be 14.0.1
    And maintenance mode should be off
    And upgrade is not required

  Scenario: Update is available - 14.0.1 to master daily
    Given the current installed version is 14.0.1
    And PHP is at least in version 7.0
    And the current channel is "beta"
    And there is an update to prerelease version of 15.0.0RC1 available
    When the CLI updater is run successfully
    And the output should contain "Update successful"
    Then the installed version should be 15.0
    And maintenance mode should be off
    And upgrade is not required

