Feature: CLI updater - stable27 base

  Scenario: Update is available - 27.0.0 RC1 to 27.0.0
    Given the current installed version is 27.0.0rc1
    And there is an update to version 27.0.0 available
    When the CLI updater is run successfully
    And the output should contain "Update successful"
    Then the installed version should be 27.1
    And maintenance mode should be off
    And upgrade is not required
