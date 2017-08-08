Feature: CLI updater - stable10 base

  Scenario: Update is available - 10.0.0 to 10.0.1
    Given the current installed version is 10.0.0
    And there is an update to version 10.0.1 available
    When the CLI updater is run successfully
    And the output should contain "Update successful"
    Then the installed version should be 10.0.1
    And maintenance mode should be off
    And upgrade is not required

  Scenario: Update is available - 10.0.0 to 11.0beta
    Given the current installed version is 10.0.0
    And there is an update to prerelease version "11.0.0beta" available
    When the CLI updater is run successfully
    And the output should contain "Update successful"
    Then the installed version should be 11.0.0
    And maintenance mode should be off
    And upgrade is not required

  Scenario: Update is available - 10.0.1 to 11.0beta
    Given the current installed version is 10.0.1
    And there is an update to prerelease version "11.0.0beta" available
    When the CLI updater is run successfully
    And the output should contain "Update successful"
    Then the installed version should be 11.0.0
    And maintenance mode should be off
    And upgrade is not required

  Scenario: Update is available - 10.0.0 to stable10 daily
    Given the current installed version is 10.0.0
    And the current channel is "daily"
    And there is an update to daily version of stable10 available
    When the CLI updater is run successfully
    And the output should contain "Update successful"
    Then the installed version should be 10.0
    And maintenance mode should be off
    And upgrade is not required

  Scenario: Update is available - 10.0.1 to stable10 daily
    Given the current installed version is 10.0.1
    And the current channel is "daily"
    And there is an update to daily version of stable10 available
    When the CLI updater is run successfully
    And the output should contain "Update successful"
    Then the installed version should be 10.0
    And maintenance mode should be off
    And upgrade is not required

  Scenario: Update is available - stable10 to stable10 daily
    Given the current installed version is stable10
    And the current channel is "daily"
    And there is an update to daily version of stable10 available
    And the version number is decreased in the config.php to enforce upgrade
    When the CLI updater is run successfully
    And the output should contain "Update successful"
    Then the installed version should be 10.0
    And maintenance mode should be off
    And upgrade is not required

  Scenario: Update is available - stable10 to 11.0.0 beta 2
    Given the current installed version is stable10
    And there is an update to prerelease version "11.0.0beta2" available
    When the CLI updater is run successfully
    And the output should contain "Update successful"
    Then the installed version should be 11.0.0.5
    And maintenance mode should be off
    And upgrade is not required

  Scenario: Update is available - 10.0.0 to 11.0.0 beta 2
    Given the current installed version is 10.0.0
    And there is an update to prerelease version "11.0.0beta2" available
    When the CLI updater is run successfully
    And the output should contain "Update successful"
    Then the installed version should be 11.0.0.5
    And maintenance mode should be off
    And upgrade is not required

  Scenario: Update is available - 10.0.1 to 11.0.0 beta 2
    Given the current installed version is 10.0.1
    And there is an update to prerelease version "11.0.0beta2" available
    When the CLI updater is run successfully
    And the output should contain "Update successful"
    Then the installed version should be 11.0.0.5
    And maintenance mode should be off
    And upgrade is not required

  Scenario: Update is available - 10.0.0 to 10.0.2RC1
    Given the current installed version is 10.0.0
    And there is an update to prerelease version "10.0.2RC1" available
    When the CLI updater is run successfully
    And the output should contain "Update successful"
    Then the installed version should be 10.0.2
    And maintenance mode should be off
    And upgrade is not required

  Scenario: Update is available - 10.0.1 to 10.0.2RC1
    Given the current installed version is 10.0.1
    And there is an update to prerelease version "10.0.2RC1" available
    When the CLI updater is run successfully
    And the output should contain "Update successful"
    Then the installed version should be 10.0.2
    And maintenance mode should be off
    And upgrade is not required


  Scenario: Update is available - 10.0.2 RC1 to 10.0.2RC1 to check if the updater will run on the RC onwards
    Given the current installed version is 10.0.2RC1
    And there is an update to prerelease version "10.0.2RC1" available
    And the version number is decreased in the config.php to enforce upgrade
    When the CLI updater is run successfully
    And the output should contain "Update successful"
    Then the installed version should be 10.0.2
    And maintenance mode should be off
    And upgrade is not required
