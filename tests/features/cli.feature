Feature: CLI updater

  Scenario: No update is available - 10.0.0
    Given the current installed version is 10.0.0
    And there is no update available
    When the CLI updater is run
    Then the installed version should be 10.0.0

  Scenario: Update is available - 10.0.0 to 10.0.1
    Given the current installed version is 10.0.0
    And there is an update to version 10.0.1 available
    When the CLI updater is run
    Then the installed version should be 10.0.1

