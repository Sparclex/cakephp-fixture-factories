<?php

namespace CakephpFixtureFactories\Test\Behat\Context;

use Behat\Behat\Context\Context;
use Cake\Datasource\EntityInterface;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use CakephpFixtureFactories\Test\Factory\PermissionFactory;
use CakephpFixtureFactories\Test\Factory\UserFactory;
use CakephpTestSuiteLight\FixtureInjector;
use CakephpTestSuiteLight\FixtureManager;

/**
 * Defines application features from the specific context.
 */
class FeatureContext extends TestCase implements Context
{
    use IntegrationTestTrait;

    /**
     * @var FixtureInjector $fixtureInjector
     */
    public $fixtureInjector;

    public function __construct($name = null, array $data = [], $dataName = '')
    {
        require_once __DIR__ . DS . '..' . DS . 'bootstrap.php';
        $this->fixtureInjector = new FixtureInjector(new FixtureManager());
    }

    /** @BeforeScenario */
    public function beforeScenario(): void
    {
        $this->fixtureInjector->startTest($this);
    }

    /**
     * @param EntityInterface $user
     */
    public function logUserIn(EntityInterface $user)
    {
        $this->session([
            'Auth' => [
                'User' => $user
            ]
        ]);
    }

    /**
     * @Given I create a user with id :id
     * @param int $id
     */
    public function persistUser(int $id)
    {
        UserFactory::make(compact('id'))->persist();
    }

    /**
     * @Given I create a permission with id :id
     * @param int $id
     */
    public function persistPermission(int $id)
    {
        PermissionFactory::make(compact('id'))->persist();
    }

    /**
     * @Given I log in with permission :permission
     * @param string $permission
     */
    public function logUserInWithPermission(string $permission)
    {
        $this->logUserIn(
            UserFactory::make()->withPermission($permission)->getEntity()
        );
    }

    /**
     * @Given I log in with permissions :permission1 and :permission2
     * @param string $permission1
     * @param string $permission2
     */
    public function logUserInWithPermissions(string $permission1, string $permission2)
    {
        $this->logUserIn(
            UserFactory::make()
                ->withPermission($permission1)
                ->withPermission($permission2)
                ->getEntity()
        );
    }

    /**
     * @Given I call get :url
     * @param string    $url
     */
    public function getUrl(string $url)
    {
        $this->get($url);
    }

    /**
     * @Then I shall be granted access.
     */
    public function assertAccessGranted()
    {
        $this->assertResponseOk();
    }

    /**
     * @Then I shall be redirected.
     */
    public function assertRedirected()
    {
        $this->assertResponseCode(302);
    }
}
