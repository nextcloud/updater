# SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
# SPDX-License-Identifier: AGPL-3.0-or-later
Feature: CLI updater - stable31 base

  Scenario: Update is available - 31.0.7 to 31.0.14
    Given the current installed version is 31.0.7
    And there is an update to version 31.0.14 available
    When the CLI updater is run successfully
    And the output should contain "Update successful"
    Then the installed version should be 31.0.14
    And maintenance mode should be off
    And upgrade is not required

Scenario: Update is available - 30.0.17 to 31.0.14
	Given the current installed version is 30.0.17
	And there is an update to version 31.0.14 available
	When the CLI updater is run successfully
	And the output should contain "Update successful"
	Then the installed version should be 31.0.14
	And maintenance mode should be off
	And upgrade is not required
