<?php

namespace CakephpFixtureFactories\Test\Behat\Context;

use Behat\Behat\Context\Context;
use Cake\Core\Configure;
use Cake\Datasource\EntityInterface;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
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

    /**
     * @BeforeSuite
     */
    public static function setUpBeforeSuite(): void
    {
//        Configure::write('TestFixtureNamespace', 'CakephpFixtureFactories\Test\Factory');
    }

    /** @BeforeScenario */
    public function beforeScenario(): void
    {
        $this->fixtureInjector->startTest($this);
    }

    /** @AfterScenario */
    public function afterScenario(): void
    {}

    public function logUser(EntityInterface $user)
    {
        $this->session([
            'Auth' => [
                'User' => $user
            ]
        ]);
    }

    /**
     * @Given I log in with permission :permission
     * @param string $permission
     */
    public function logUserInWithPermission(string $permission)
    {
        $this->logUser(
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
        $this->logUser(
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
    public function assertHasAccess()
    {
        $this->assertResponseOk();
    }

    /**
     * @Then I shall be redirected.
     */
    public function assertHasNoAccess()
    {
        $this->assertResponseCode(302);
    }
}
