Feature: CLI updater - stable14 base

  Scenario: Update is available - 15.0.0 beta 1 to 15.0.0 RC 1
    Given the current installed version is 15.0.0beta1
    And there is an update to prerelease version "15.0.0RC1" available
    And the version number is decreased in the config.php to enforce upgrade
    When the CLI updater is run successfully
    And the output should contain "Update successful"
    Then the installed version should be 15.0
    And maintenance mode should be off
    And upgrade is not required

  Scenario: Update is available - 15.0.0 to master daily
    Given the current installed version is 15.0.0RC1
    And PHP is at least in version 7.0
    And the current channel is "beta"
    And there is an update to version 16.0.3 available
    When the CLI updater is run successfully
    And the output should contain "Update successful"
    Then the installed version should be 16.0
    And maintenance mode should be off
    And upgrade is not required

