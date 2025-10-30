# SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
# SPDX-License-Identifier: AGPL-3.0-or-later
Feature: CLI updater - stable26 base

  Scenario: Update is available - 26.0.0 to 26.0.13
    Given the current installed version is 26.0.0
    And there is an update to version 26.0.13 available
    When the CLI updater is run successfully
    And the output should contain "Update successful"
    Then the installed version should be 26.0.13
    And maintenance mode should be off
    And upgrade is not required

  Scenario: Update is available - 26.0.13 to 27.1.11
    Given the current installed version is 26.0.13
    And PHP is at least in version 8.2
    And the current channel is "beta"
    And there is an update to version 27.1.11 available
    When the CLI updater is run successfully
    And the output should contain "Update successful"
    Then the installed version should be 27.1.11
    And maintenance mode should be off
    And upgrade is not required

