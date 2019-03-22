Feature: CLI updater

  Scenario: No update is available - 12.0.0
    Given the current version is 12.0.0
    When the CLI updater is run
    Then the output should contain "Could not find config.php. Is this file in the "updater" subfolder of Nextcloud?"

  Scenario: No update is available - 12.0.0
    Given the current installed version is 12.0.0
    And there is no update available
    When the CLI updater is run successfully
    Then the installed version should be 12.0.0
    And maintenance mode should be off
    And upgrade is not required

  Scenario: Update is available - 12.0.0 to 12.0.1
    Given the current installed version is 12.0.0
    And there is an update to version 12.0.1 available
    When the CLI updater is run successfully
    Then the installed version should be 12.0.1
    And maintenance mode should be off
    And upgrade is not required

  Scenario: Invalid update is available - 12.0.0 to 12.0.503
    Given the current installed version is 12.0.0
    And there is an update to version 12.0.503 available
    When the CLI updater is run
    Then the return code should not be 0
    And the output should contain "Download failed - Not Found (HTTP 404)"
    And the installed version should be 12.0.0
    And maintenance mode should be off
    And upgrade is not required

  Scenario: Update without valid signature is being offered - 11.0.0 to 11.0.1
    Given the current installed version is 11.0.0
    # This works because 11.0.1 is not in the signature list
    And there is an update to version 11.0.1 available
    When the CLI updater is run
    Then the return code should not be 0
    And the output should contain "Signature of update is not valid"
    And the installed version should be 11.0.0
    And maintenance mode should be off
    And upgrade is not required

  Scenario: Update to older version - 12.0.0 to 11.0.2
    Given the current installed version is 12.0.0
    And there is an update to version 11.0.2 available
    When the CLI updater is run
    Then the return code should not be 0
    And the output should contain "Downloaded version is lower than installed version"
    And the installed version should be 12.0.0
    And maintenance mode should be off
    And upgrade is not required

  Scenario: Update is available but autoupdate is disabled - 12.0.0 to 12.0.1
    Given the current installed version is 12.0.0
    And the autoupdater is disabled
    And there is an update to version 12.0.1 available
    When the CLI updater is run
    Then the installed version should be 12.0.0
    And maintenance mode should be off
    And upgrade is not required

  Scenario: Update is available and apps2 folder is there and configured - 12.0.0 to 12.0.1
    Given the current installed version is 12.0.0
    And there is an update to version 12.0.1 available
    And there is a folder called "apps2"
    And there is a config for a secondary apps directory called "apps2"
    When the CLI updater is run successfully
    Then the installed version should be 12.0.1
    And maintenance mode should be off
    And upgrade is not required

  Scenario: Update is available and apps2 folder is there and not configured - 12.0.0 to 12.0.1
    Given the current installed version is 12.0.0
    And there is an update to version 12.0.1 available
    And there is a folder called "apps2"
    When the CLI updater is run
    Then the return code should not be 0
    And the output should contain "The following extra files have been found"
    And the output should contain "apps2"
    And the installed version should be 12.0.0
    And maintenance mode should be off
    And upgrade is not required
