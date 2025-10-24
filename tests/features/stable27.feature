# SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
# SPDX-License-Identifier: AGPL-3.0-or-later
Feature: CLI updater - stable27 base

  Scenario: Update is available - 27.0.0 beta1 to 27.0.1 RC1
    Given the current installed version is 27.0.0beta1
    And there is an update to prerelease version "27.0.1rc1" available
    And the version number is decreased in the config.php to enforce upgrade
    When the CLI updater is run successfully
    And the output should contain "Update successful"
    Then the installed version should be 27.0
    And maintenance mode should be off
    And upgrade is not required

  Scenario: Update is available but unexpected folder found - 27.1.6 to 27.1.11
    Given the current installed version is 27.1.6
    And there is an update to version 27.1.11 available
    And there is a folder called "test123"
    When the CLI updater is run
    Then the return code should not be 0
    And the output should contain "The following extra files have been found"
    Then the installed version should be 27.1.6
    And maintenance mode should be off
    And upgrade is not required

  Scenario: Update is available and .well-known folder exist - 27.1.6 to 27.1.11
    Given the current installed version is 27.1.6
    And there is an update to version 27.1.11 available
    And there is a folder called ".well-known"
    When the CLI updater is run successfully
    And the output should contain "Update successful"
    Then the installed version should be 27.1.11
    And maintenance mode should be off
    And upgrade is not required

  Scenario: Update is available and .rnd file exist - 27.1.6 to 27.1.11
    Given the current installed version is 27.1.6
    And there is an update to version 27.1.11 available
    And there is a folder called ".rnd"
    When the CLI updater is run successfully
    And the output should contain "Update successful"
    Then the installed version should be 27.1.11
    And maintenance mode should be off
    And upgrade is not required

  Scenario: Update is available - 27.1.0 to beta
    Given the current installed version is 27.1.0rc1
    And PHP is at least in version 8.0
    And the current channel is "beta"
    And there is an update to version 28.0.14 available
    When the CLI updater is run successfully
    And the output should contain "Update successful"
    Then the installed version should be 28.0
    And maintenance mode should be off
    And upgrade is not required

