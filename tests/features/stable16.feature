Feature: CLI updater - stable16 base

  Scenario: Update is available - 16.0.1 to 16.0.3
    Given the current installed version is 16.0.0
    And there is an update to version 16.0.3 available
    When the CLI updater is run successfully
    And the output should contain "Update successful"
    Then the installed version should be 16.0
    And maintenance mode should be off
    And upgrade is not required

  Scenario: Update is available - 16.0.0 to master daily
    Given the current installed version is 16.0.0
    And the current channel is "daily"
    And there is an update to daily version of master available
# TODO broken due to files_texteditor removal
#    When the CLI updater is run successfully
#    And the output should contain "Update successful"
#    Then the installed version should be 17.0
#    And maintenance mode should be off
#    And upgrade is not required


