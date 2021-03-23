Feature: CLI updater - master base

  Scenario: Update is available - master to master daily
    Given the current installed version is master
    And the current channel is "daily"
    And there is an update to daily version of master available
    And the version number is decreased in the config.php to enforce upgrade
    When the CLI updater is run successfully
    And the output should contain "Update successful"
    Then the installed version should be 22.0
    And maintenance mode should be off
    And upgrade is not required
