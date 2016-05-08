@mod @mod_pcast @file_upload @javascript
Feature: Pcast reset
  In order to rate past podcast activities
  As a teacher
  I need to enable ratings.

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | teacher | Teacher | 1 | teacher1@example.com |
      | student | Student | 1 | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher | C1 | editingteacher |
      | student | C1 | student |
    And the following "activities" exist:
      | activity | course | idnumber | name              | intro                    | userscanpost | requireapproval |
      | pcast    | C1     | pcast    | Test podcast name | Test podcast description | 1            | 0               |

  Scenario: Use ratings to rate student episodes
    Given I log in as "teacher"
    And I follow "Course 1"
    And I follow "Test podcast name"
    And I navigate to "Edit settings" node in "Podcast administration"
    And I expand all fieldsets
    And I set the field "Aggregate type" to "Count of ratings" 
    And I set the field "id_scale_modgrade_type" to "Scale"
    And I press "Save and display"
    And I log out
    And I log in as "student"
    And I follow "Course 1"
    And I follow "Test podcast name"
    And I press "Add a new episode"
    And I set the following fields to these values:
      | Title | Test episode name |
      | Summary | Test episode summary |
    And I upload "mod/pcast/tests/fixtures/sample.mp3" file to "Media file" filemanager
    And I press "Save changes"
    And I log out
    When I log in as "teacher"
    And I follow "Course 1"
    And I follow "Test podcast name"
    And I follow "View"
    And I follow "Rate"
    And I set the field "rating" to "Mostly connected knowing"
    And I log out
    And I log in as "student"
    And I follow "Course 1"
    And I follow "Test podcast name"
    And I follow "View"
    Then I should see "1" in the "Total ratings" "table_row"
