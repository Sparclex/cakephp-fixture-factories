Feature: User permission

  Background:
   Given I create a user with id 123

  Scenario:
    And I log in with permission 'Users'
    When I call get 'users/view/123'
    Then I shall be granted access.

  Scenario:
    Given I log in with permission 'Admin'
    When I call get 'users/view/123'
    Then I shall be granted access.

  Scenario:
    Given I log in with permission 'Guru'
    When I call get 'users/view/123'
    Then I shall be granted access.

  Scenario:
    Given I log in with permission 'Foo'
    When I call get 'users/view/123'
    Then I shall be redirected.

  Scenario:
    Given I log in with permissions 'Foo' and 'Users'
    When I call get 'users/view/123'
    Then I shall be granted access.

  Scenario:
    Given I log in with permissions 'Foo' and 'Bar'
    When I call get 'users/view/123'
    Then I shall be redirected.

  Scenario:
    Given I create a permission with id 99
    And I log in with permission 'Users'
    When I call get 'permissions/view/99'
    Then I shall be redirected.

  Scenario:
    Given I create a permission with id 9
    And  I log in with permission 'Permissions'
    When I call get 'permissions/view/9'
    Then I shall be granted access.