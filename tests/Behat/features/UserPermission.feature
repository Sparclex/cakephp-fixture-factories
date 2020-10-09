Feature: User permission

  Scenario:
    Given I log in with permission 'Users'
    When I call get 'users/view/1'
    Then I shall be granted access.

  Scenario:
    Given I log in with permission 'Admin'
    When I call get 'users/view/1'
    Then I shall be granted access.

  Scenario:
    Given I log in with permission 'Guru'
    When I call get 'users/view/1'
    Then I shall be granted access.

  Scenario:
    Given I log in with permission 'Foo'
    When I call get 'users/view/1'
    Then I shall be redirected.

  Scenario:
    Given I log in with permissions 'Permissions' and 'Users'
    When I call get 'users/view/1'
    Then I shall be granted access.

  Scenario:
    Given I log in with permissions 'Foo' and 'Bar'
    When I call get 'users/view/1'
    Then I shall be redirected.

  Scenario:
    Given I log in with permission 'Users'
    When I call get 'permissions/view/1'
    Then I shall be redirected.