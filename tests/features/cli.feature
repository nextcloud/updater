Feature: CLI updater

  Scenario: No update is available - 10.0.0
    Given the current version is 10.0.0
    When the CLI updater is run
    Then the output should contain "Could not find config.php. Is this file in the "updater" subfolder of Nextcloud?"

  Scenario: No update is available - 10.0.0
    Given the current installed version is 10.0.0
    And there is no update available
    When the CLI updater is run successfully
    Then the installed version should be 10.0.0
    And maintenance mode should be off
    And upgrade is not required

  Scenario: Update is available - 10.0.0 to 10.0.1
    Given the current installed version is 10.0.0
    And there is an update to version 10.0.1 available
    When the CLI updater is run successfully
    Then the installed version should be 10.0.1
    And maintenance mode should be off
    And upgrade is not required

  Scenario: Invalid update is available - 10.0.0 to 10.0.503
    Given the current installed version is 10.0.0
    And there is an update to version 10.0.503 available
    When the CLI updater is run
    Then the return code should not be 0
    And the output should contain "Download failed - Not Found (HTTP 404)"
    And the installed version should be 10.0.0
    # known issue:
    And maintenance mode should be on
    # TODO - it should be:
    #And maintenance mode should be off
    And upgrade is not required

  Scenario: Update without valid signature is being offered - 10.0.0 to 10.0.2
    Given the current installed version is 10.0.0
    # This works because 10.0.2 is not in the signature list
    And there is an update to version 10.0.2 available
    When the CLI updater is run
    Then the return code should not be 0
    And the output should contain "Signature of update is not valid"
    And the installed version should be 10.0.0
# known issue:
    And maintenance mode should be on
# TODO - it should be:
#And maintenance mode should be off
    And upgrade is not required

  Scenario: Update to older version - 10.0.0 to 9.0.54
    Given the current installed version is 10.0.0
    And there is an update to version 9.0.54 available
    When the CLI updater is run
    Then the return code should not be 0
    And the output should contain "Downloaded version is lower than installed version"
    And the installed version should be 10.0.0
# known issue:
    And maintenance mode should be on
# TODO - it should be:
#And maintenance mode should be off
    And upgrade is not required

  Scenario: Update is available but autoupdate is disabled - 10.0.0 to 10.0.1
    Given the current installed version is 10.0.0
    And the autoupdater is disabled
    And there is an update to version 10.0.1 available
    When the CLI updater is run
    Then the installed version should be 10.0.0
    And maintenance mode should be off
    And upgrade is not required
