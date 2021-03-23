Feature: CLI updater - stable20 base

  Scenario: Update is available - 20.0.0 beta 1 to 20.0.0 RC 1
    Given the current installed version is 20.0.0beta1
    And there is an update to prerelease version "20.0.0RC1" available
    And the version number is decreased in the config.php to enforce upgrade
    When the CLI updater is run successfully
    And the output should contain "Update successful"
    Then the installed version should be 20.0
    And maintenance mode should be off
    And upgrade is not required

  Scenario: Update is available but unexpected folder found - 20.0.7 to 20.0.8
    Given the current installed version is 20.0.7
    And there is an update to version 20.0.8 available
    And there is a folder called "test123"
    When the CLI updater is run
    Then the return code should not be 0
    And the output should contain "The following extra files have been found"
    Then the installed version should be 20.0.7
    And maintenance mode should be off
    And upgrade is not required

  Scenario: Update is available and .well-known folder exist - 20.0.7 to 20.0.8
    Given the current installed version is 20.0.7
    And there is an update to version 20.0.8 available
    And there is a folder called ".well-known"
    When the CLI updater is run successfully
    And the output should contain "Update successful"
    Then the installed version should be 20.0.8
    And maintenance mode should be off
    And upgrade is not required

  Scenario: Update is available and .rnd file exist - 20.0.7 to 20.0.10
    Given the current installed version is 20.0.7
    And there is an update to version 20.0.8 available
    And there is a folder called ".rnd"
    When the CLI updater is run successfully
    And the output should contain "Update successful"
    Then the installed version should be 20.0.8
    And maintenance mode should be off
    And upgrade is not required

  Scenario: Update is available - 20.0.0 to beta
    Given the current installed version is 20.0.0RC1
    And PHP is at least in version 7.3
    And the current channel is "beta"
    And there is an update to version 21.0.0 available
    When the CLI updater is run successfully
    And the output should contain "Update successful"
    Then the installed version should be 21.0
    And maintenance mode should be off
    And upgrade is not required

