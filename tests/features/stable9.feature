Feature: CLI updater - stable9 base

  Scenario: Update is available - 9.0.50 to 9.0.54
    Given the current installed version is 9.0.50
    And there is an update to version 9.0.54 available
    When the CLI updater is run successfully
    And the output should contain "Update successful"
    Then the installed version should be 9.0.54
    And maintenance mode should be off
    And upgrade is not required

  Scenario: Update is available - 9.0.51 to 9.0.54
    Given the current installed version is 9.0.51
    And there is an update to version 9.0.54 available
    When the CLI updater is run successfully
    And the output should contain "Update successful"
    Then the installed version should be 9.0.54
    And maintenance mode should be off
    And upgrade is not required

  Scenario: Update is available - 9.0.52 to 9.0.54
    Given the current installed version is 9.0.52
    And there is an update to version 9.0.54 available
    When the CLI updater is run successfully
    And the output should contain "Update successful"
    Then the installed version should be 9.0.54
    And maintenance mode should be off
    And upgrade is not required

  Scenario: Update is available - 9.0.53 to 9.0.54
    Given the current installed version is 9.0.53
    And there is an update to version 9.0.54 available
    When the CLI updater is run successfully
    And the output should contain "Update successful"
    Then the installed version should be 9.0.54
    And maintenance mode should be off
    And upgrade is not required

  Scenario: Update is available - 9.0.53 to stable9 daily
    Given the current installed version is 9.0.53
    And there is an update to daily version of stable9 available
    When the CLI updater is run successfully
    And the output should contain "Update successful"
    Then the installed version should be 9.0.54
    And maintenance mode should be off
    And upgrade is not required

  Scenario: Update is available - 9.0.53 to 9.0.55 RC1
    Given the current installed version is 9.0.53
    And there is an update to prerelease version 9.0.55RC1 available
    When the CLI updater is run successfully
    And the output should contain "Update successful"
    Then the installed version should be 9.0.55
    And maintenance mode should be off
    And upgrade is not required

  Scenario: Update is available - 9.0.54 to 9.0.55 RC1
    Given the current installed version is 9.0.54
    And there is an update to prerelease version 9.0.55RC1 available
    When the CLI updater is run successfully
    And the output should contain "Update successful"
    Then the installed version should be 9.0.55
    And maintenance mode should be off
    And upgrade is not required

  Scenario: Update is available - 9.0.55 RC1 to 9.0.55 RC1 to check if the updater will run on the RC onwards
    Given the current installed version is 9.0.55RC1
    And there is an update to prerelease version 9.0.55RC1 available
    And the version number is decreased in the config.php to enforce upgrade
    When the CLI updater is run successfully
    And the output should contain "Update successful"
    Then the installed version should be 9.0.55
    And maintenance mode should be off
    And upgrade is not required

  Scenario: Update is available - 9.0.54 to stable9 daily
    Given the current installed version is 9.0.54
    And there is an update to daily version of stable9 available
    And the version number is decreased in the config.php to enforce upgrade
    When the CLI updater is run successfully
    And the output should contain "Update successful"
    Then the installed version should be 9.0.55
    And maintenance mode should be off
    And upgrade is not required

  Scenario: Update is available - stable9 to stable9 daily
    Given the current installed version is stable9
    And there is an update to daily version of stable9 available
    And the version number is decreased in the config.php to enforce upgrade
    When the CLI updater is run successfully
    And the output should contain "Update successful"
    Then the installed version should be 9.0.55
    And maintenance mode should be off
    And upgrade is not required

  Scenario: Update is available - 9.0.50 to 10.0.1
    Given the current installed version is 9.0.50
    And there is an update to version 10.0.1 available
    When the CLI updater is run successfully
    And the output should contain "Update successful"
    Then the installed version should be 10.0.1
    And maintenance mode should be off
    And upgrade is not required

  Scenario: Update is available - 9.0.51 to 10.0.1
    Given the current installed version is 9.0.51
    And there is an update to version 10.0.1 available
    When the CLI updater is run successfully
    And the output should contain "Update successful"
    Then the installed version should be 10.0.1
    And maintenance mode should be off
    And upgrade is not required

  Scenario: Update is available - 9.0.52 to 10.0.1
    Given the current installed version is 9.0.52
    And there is an update to version 10.0.1 available
    When the CLI updater is run successfully
    And the output should contain "Update successful"
    Then the installed version should be 10.0.1
    And maintenance mode should be off
    And upgrade is not required

  Scenario: Update is available - 9.0.53 to 10.0.1
    Given the current installed version is 9.0.53
    And there is an update to version 10.0.1 available
    When the CLI updater is run successfully
    And the output should contain "Update successful"
    Then the installed version should be 10.0.1
    And maintenance mode should be off
    And upgrade is not required

  Scenario: Update is available - 9.0.53 to 10.0.2RC1
    Given the current installed version is 9.0.53
    And there is an update to prerelease version 10.0.2RC1 available
    When the CLI updater is run successfully
    And the output should contain "Update successful"
    Then the installed version should be 10.0.2
    And maintenance mode should be off
    And upgrade is not required

  Scenario: Update is available - 9.0.54 to 10.0.2RC1
    Given the current installed version is 9.0.54
    And there is an update to prerelease version 10.0.2RC1 available
    When the CLI updater is run successfully
    And the output should contain "Update successful"
    Then the installed version should be 10.0.2
    And maintenance mode should be off
    And upgrade is not required

  Scenario: Update is available - 9.0.54 to stable10 daily
    Given the current installed version is 9.0.54
    And there is an update to daily version of stable10 available
    When the CLI updater is run successfully
    And the output should contain "Update successful"
    Then the installed version should be 10.0.2
    And maintenance mode should be off
    And upgrade is not required