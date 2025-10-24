# SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
# SPDX-License-Identifier: AGPL-3.0-or-later
Feature: CLI updater - stable28 base

  Scenario: Update is available - 28.0.0 beta 3 to 28.0.0 beta 4
    Given the current installed version is 28.0.0beta3
    And there is an update to prerelease version "28.0.0beta4" available
    And the version number is decreased in the config.php to enforce upgrade
    When the CLI updater is run successfully
    And the output should contain "Update successful"
    Then the installed version should be 28.0
    And maintenance mode should be off
    And upgrade is not required

  Scenario: Update is available - 28.0.0 RC 1 to 28.0.14
    Given the current installed version is 28.0.0rc1
    And there is an update to version 28.0.14 available
    When the CLI updater is run successfully
    And the output should contain "Update successful"
    Then the installed version should be 28.0
    And maintenance mode should be off
    And upgrade is not required
