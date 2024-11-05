# SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
# SPDX-License-Identifier: AGPL-3.0-or-later
Feature: CLI updater - stable25 base

  Scenario: Update is available - 25.0.0 beta 1 to 25.0.0 rc 1
    Given the current installed version is 25.0.0beta1
    And there is an update to prerelease version "25.0.0rc1" available
    And the version number is decreased in the config.php to enforce upgrade
    When the CLI updater is run successfully
    And the output should contain "Update successful"
    Then the installed version should be 25.0
    And maintenance mode should be off
    And upgrade is not required

  Scenario: Update is available but unexpected folder found - 25.0.6 to 25.0.7
    Given the current installed version is 25.0.6
    And there is an update to version 25.0.7 available
    And there is a folder called "test123"
    When the CLI updater is run
    Then the return code should not be 0
    And the output should contain "The following extra files have been found"
    Then the installed version should be 25.0.6
    And maintenance mode should be off
    And upgrade is not required

  Scenario: Update is available and .well-known folder exist - 25.0.6 to 25.0.7
    Given the current installed version is 25.0.6
    And there is an update to version 25.0.7 available
    And there is a folder called ".well-known"
    When the CLI updater is run successfully
    And the output should contain "Update successful"
    Then the installed version should be 25.0.7
    And maintenance mode should be off
    And upgrade is not required

  Scenario: Update is available and .rnd file exist - 25.0.6 to 25.0.7
    Given the current installed version is 25.0.6
    And there is an update to version 25.0.7 available
    And there is a folder called ".rnd"
    When the CLI updater is run successfully
    And the output should contain "Update successful"
    Then the installed version should be 25.0.7
    And maintenance mode should be off
    And upgrade is not required

  Scenario: Update is available - 25.0.0 to beta
    Given the current installed version is 25.0.0rc1
    And PHP is at least in version 8.0
    And the current channel is "beta"
    And there is an update to version 26.0.0 available
    When the CLI updater is run successfully
    And the output should contain "Update successful"
    Then the installed version should be 26.0
    And maintenance mode should be off
    And upgrade is not required

