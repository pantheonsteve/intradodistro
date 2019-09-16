@lightning @api @lightning_api
Feature: JSON API for decoupled applications

  @23138ee5 @orca_public
  Scenario: Viewing a content entity as JSON
    Given I am logged in as an administrator
    And test content:
      | title  |
      | Foobar |
    When I visit "/admin/content"
    And I click "View JSON"
    Then the response status code should be 200

  @160f8533 @orca_public
  Scenario: Viewing a config entity as JSON
    Given I am logged in as an administrator
    When I visit "/admin/structure/types"
    And I click "View JSON"
    Then the response status code should be 200

  @c7366331
  Scenario: Accessing documentation on path alias
    Given I am logged in as an administrator
    When I visit "/api-docs"
    Then the response status code should be 200

  @5255d0d7
  Scenario: Viewing content type documentation
    Given I am logged in as an administrator
    When I visit "/admin/structure/types"
    And I click "View API documentation"
    Then the response status code should be 200
