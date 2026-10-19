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

  Scenario Outline: Text that only looks like part of an address is not linked
    Given the following "activities" exist:
      | activity | course | name   | content   |
      | page     | C1     | Page 1 | <content> |
    When I am on the "Page 1" "page activity" page logged in as student1
    Then "a[href^='mailto:']" "css_element" should not exist
    And I should see "<visible>"

    Examples:
      | content                                        | visible                          |
      | Go to https://example.com/?to=jane@example.com | example.com/?to=jane@example.com |
      | Go to https://user@example.com/x               | user@example.com/x               |
      | Write to a?subject=Hi&cc=b@example.com         | a?subject=Hi&cc=b@example.com    |
      | Upload logo@2x.png and install react@18.3.1    | logo@2x.png                      |
      | Clone git@github.com:org/repo                  | git@github.com:org/repo          |

  Scenario: An address next to sentence punctuation links only the address
    Given the following "activities" exist:
      | activity | course | name   | content                                           |
      | page     | C1     | Page 1 | Email (jane@example.com), or o'brien@example.com. |
    When I am on the "Page 1" "page activity" page logged in as student1
    Then "a[href='mailto:jane@example.com']" "css_element" should exist
    And "a[href=\"mailto:o'brien@example.com\"]" "css_element" should exist

  Scenario Outline: A valid accented address is linked as a whole
    Given the following "activities" exist:
      | activity | course | name   | content            |
      | page     | C1     | Page 1 | Write to <address> |
    When I am on the "Page 1" "page activity" page logged in as student1
    Then "a[href='mailto:<href>']" "css_element" should exist
    And I should see "<address>" in the "a[href='mailto:<href>']" "css_element"

    Examples:
      | address          | href                       |
      | tést@example.com | t%C3%A9st@example.com      |
      | éva@example.com  | %C3%A9va@example.com       |
      | jöe@exämple.de   | j%C3%B6e@xn--exmple-cua.de |

  Scenario Outline: An invalid accented address is not linked at all
    Given the following "activities" exist:
      | activity | course | name   | content            |
      | page     | C1     | Page 1 | Write to <address> |
    When I am on the "Page 1" "page activity" page logged in as student1
    Then "a[href^='mailto:']" "css_element" should not exist
    And I should see "<address>"

    Examples:
      | address             |
      | tést@exämple        |
      | tést@-example.com   |
      | tést..x@example.com |
      | .tést@example.com   |

  Scenario: Addresses in code samples are not linked
    Given the following "activities" exist:
      | activity | course | name   | content                                                     |
      | page     | C1     | Page 1 | <pre>ssh jane@example.com</pre><code>bob@example.com</code> |
    When I am on the "Page 1" "page activity" page logged in as student1
    Then "a[href^='mailto:']" "css_element" should not exist
