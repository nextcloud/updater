# SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
# SPDX-License-Identifier: AGPL-3.0-or-later
Feature: CLI updater - user.ini retention test

  Scenario: User.ini retention after update
    Given the current installed version is 26.0.0rc1
    Given the config key "user_ini_additional_lines" is set to "upload_max_filesize = 10G\npost_max_size = 10G" of type "string"
    And there is an update to version 26.0.13 available
    When the CLI updater is run successfully
    And the output should contain "Update successful"
    Then the installed version should be 26.0
    And maintenance mode should be off
    And upgrade is not required
    And the user ini file contains "upload_max_filesize = 10G"
    And the user ini file contains "post_max_size = 10G"
