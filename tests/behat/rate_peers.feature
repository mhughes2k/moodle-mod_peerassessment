@mod @mod_peerassessment
Feature: Users can rate members of their groups.

@javascript
Scenario:  User rates their peers
    Given the following "users" exist:
        | username | firstname | lastname | email |
        | teacher  | Teacher   | Teacher  | teacher@localhost.localdomain |
        | student1 | student   | student1 | student1@localhost.localdomain |
        | student2 | student   | student2 | student2@localhost.localdomain |
        | student3 | student   | student3 | student3@localhost.localdomain |
        | student4 | student   | student4 | student4@localhost.localdomain |
        | student5 | student   | student5 | student5@localhost.localdomain |
    And the following "courses" exist:
        | fullname | shortname | category |
        | Course 1 | C1 | 0 |
    And the following "course enrolments" exist:
        | user    | course | role |
        | teacher | C1     | editingteacher |
        | student1| C1     | student |
        | student2| C1     | student |
        | student3| C1     | student |
        | student4| C1     | student |
        | student5| C1     | student |
    And the following "groups" exist:
        | name    | course | idnumber |
        | Group 1 | C1     | g1 |
    And the following "group members" exist:
        | user | group |
        | student1 | g1 |
        | student2 | g1 |
        | student3 | g1 |
        | student4 | g1 |
        | student5 | g1 |
    #And the following "scales" exist:
    #    | name | scale |
    #    | scale1 | 1,2,3,4,5|
    And the following "activities" exist:
        | activity      | name | intro           | idnumber | groupmode | course | ratingscale |
        | peerassessment| PA 1 | Peer assessment | pa1      | 1         | C1     | 5 |
    # May have to automate setting up the assignment by the admin
    And I log in as "student1"
    And I follow "Course 1"
    And I follow "PA 1"
    Then I should see "PA 1"
    And I should see "student1, student ("
    And I should see "student2, student ("
    And I should see "student3, student ("
    And I should see "student4, student ("
    And I should see "student5, student ("