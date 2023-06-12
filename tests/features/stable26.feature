Feature: CLI updater - stable26 base

  Scenario: Update is available - 26.0.0 beta 3 to 26.0.0 beta 4
    Given the current installed version is 26.0.0beta3
    And there is an update to prerelease version "26.0.0beta4" available
    And the version number is decreased in the config.php to enforce upgrade
    When the CLI updater is run successfully
    And the output should contain "Update successful"
    Then the installed version should be 26.0
    And maintenance mode should be off
    And upgrade is not required

  Scenario: Update is available - 26.0.0 RC 1 to 26.0.0
    Given the current installed version is 26.0.0rc1
    And there is an update to version 26.0.0 available
    When the CLI updater is run successfully
    And the output should contain "Update successful"
    Then the installed version should be 26.0
    And maintenance mode should be off
    And upgrade is not required
