# SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
# SPDX-License-Identifier: AGPL-3.0-or-later
Feature: CLI updater

  Scenario: No update is available - 26.0.0
    Given the current version is 26.0.0
    When the CLI updater is run
    Then the output should contain "Could not find config.php"

  Scenario: No update is available - 26.0.0
    Given the current installed version is 26.0.0
    And there is no update available
    When the CLI updater is run successfully
    Then the installed version should be 26.0.0
    And maintenance mode should be off
    And upgrade is not required

  Scenario: Update is available - 26.0.0 to 26.0.13
    Given the current installed version is 26.0.0
    And there is an update to version 26.0.13 available
    When the CLI updater is run successfully
    Then the installed version should be 26.0.1
    And maintenance mode should be off
    And upgrade is not required

  Scenario: Invalid update is available - 26.0.0 to 26.0.503
    Given the current installed version is 26.0.0
    And there is an update to version 26.0.503 available
    When the CLI updater is run
    Then the return code should not be 0
    And the output should contain "All downloads failed."
    And the installed version should be 26.0.0
    And maintenance mode should be off
    And upgrade is not required

  Scenario: Update without valid signature is being offered - 26.0.0 to 26.0.3
    Given the current installed version is 26.0.0
    # This works because 26.0.3 is in the signature list with an invalid signature
    And there is an update to version 26.0.3 available
    When the CLI updater is run
    Then the return code should not be 0
    And the output should contain "Signature of update is not valid"
    And the installed version should be 26.0.0
    And maintenance mode should be off
    And upgrade is not required

  Scenario: Update to older version - 26.0.0 to 25.0.1
    Given the current installed version is 26.0.0
    And there is an update to version 25.0.1 available
    When the CLI updater is run
    Then the return code should not be 0
    And the output should contain "Downloaded version is lower than installed version"
    And the installed version should be 26.0.0
    And maintenance mode should be off
    And upgrade is not required

  Scenario: Update is available but autoupdate is disabled - 26.0.0 to 26.0.1
    Given the current installed version is 26.0.0
    And the autoupdater is disabled
    And there is an update to version 26.0.1 available
    When the CLI updater is run
    Then the installed version should be 26.0.0
    And maintenance mode should be off
    And upgrade is not required

  Scenario: Update is available and apps2 folder is there and configured - 26.0.0 to 26.0.13
    Given the current installed version is 26.0.0
    And there is an update to version 26.0.13 available
    And there is a folder called "apps2"
    And there is a config for a secondary apps directory called "apps2"
    When the CLI updater is run successfully
    Then the installed version should be 26.0.1
    And maintenance mode should be off
    And upgrade is not required

  Scenario: Update is available and apps2 folder is there and not configured - 26.0.0 to 26.0.13
    Given the current installed version is 26.0.0
    And there is an update to version 26.0.13 available
    And there is a folder called "apps2"
    When the CLI updater is run
    Then the return code should not be 0
    And the output should contain "The following extra files have been found"
    And the output should contain "apps2"
    And the installed version should be 26.0.0
    And maintenance mode should be off
    And upgrade is not required
