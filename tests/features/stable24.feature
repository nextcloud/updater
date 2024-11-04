# SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
# SPDX-License-Identifier: AGPL-3.0-or-later
Feature: CLI updater - stable24 base

  Scenario: Update is available - 24.0.0 to 24.0.1
    Given the current installed version is 24.0.0
    And there is an update to version 24.0.1 available
    When the CLI updater is run successfully
    And the output should contain "Update successful"
    Then the installed version should be 24.0.1
    And maintenance mode should be off
    And upgrade is not required

  Scenario: Update is available - 24.0.1 to 25.0.0
    Given the current installed version is 24.0.1
    And PHP is at least in version 8.0
    And the current channel is "beta"
    And there is an update to version 25.0.0 available
    When the CLI updater is run successfully
    And the output should contain "Update successful"
    Then the installed version should be 25.0.0
    And maintenance mode should be off
    And upgrade is not required

