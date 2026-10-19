@filter @filter_emailautolink
Feature: Auto-link email addresses in content
  In order to contact people mentioned in course content
  As a student
  I need plain-text email addresses to become links to exactly that address, without damaging the content around them

  Background:
    Given the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "users" exist:
      | username | firstname | lastname |
      | student1 | Student   | One      |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
    And the "emailautolink" filter is "on"

  Scenario Outline: An address inside an attribute containing a pipe is left alone
    Given the following "activities" exist:
      | activity | course | name  | intro     |
      | forum    | C1     | Forum | <content> |
    When I am on the "Forum" "forum activity" page logged in as student1
    Then "<element>" "css_element" should exist
    And "a[href^='mailto:']" "css_element" should not exist

    Examples:
      | content                                                | element                           |
      | See <img src="a.png" alt="a\|jane@example.com"> here   | img[alt='a\|jane@example.com']    |
      | See <span title="a\|jane@example.com">this</span> here | span[title='a\|jane@example.com'] |
