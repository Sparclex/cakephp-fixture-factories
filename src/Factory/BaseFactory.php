<?php
declare(strict_types=1);

/**
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) 2020 Juan Pablo Ramirez and Nicolas Masson
 * @link          https://webrider.de/
 * @since         1.0.0
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace CakephpFixtureFactories\Factory;

use Cake\Datasource\EntityInterface;
use Cake\Datasource\ResultSetInterface;
use Cake\ORM\Exception\PersistenceFailedException;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use CakephpFixtureFactories\Error\PersistenceException;
use Exception;
use Faker\Factory;
use Faker\Generator;
use InvalidArgumentException;
use function array_merge;
use function is_array;
use function is_callable;

/**
 * Class BaseFactory
 *
 * @package CakephpFixtureFactories\Factory
 */
abstract class BaseFactory
{
    /**
     * @var Generator|null
     */
    static private $faker;
    /**
     * @deprecated
     * @var bool
     */
    static protected $applyListenersAndBehaviors = false;
    /**
     * @var array
     */
    protected $marshallerOptions = [
        'validate' => false,
        'forceNew' => true,
        'accessibleFields' => ['*' => true],
    ];
    /**
     * @var array
     */
    protected $saveOptions = [
        'checkRules' => false,
        'atomic' => false,
        'checkExisting' => false
    ];
    /**
     * @var bool
     */
    protected $withModelEvents = false;
    /**
     * The number of records the factory should create
     *
     * @var int
     */
    private $times = 1;
    /**
     * The data compiler gathers the data from the
     * default template, the injection and patched data
     * and compiles it to produce the data feeding the
     * entities of the Factory
     *
     * @var DataCompiler
     */
    private $dataCompiler;
    /**
     * Helper to check and build data in associations
     * @var AssociationBuilder
     */
    private $associationBuilder;
    /**
     * Handles the events at the model and behavior level
     * for the table on which the factories will be built
     *
     * @var EventCollector
     */
    private $eventCompiler;

    /**
     * BaseFactory constructor.
     */
    final protected function __construct()
    {
        $this->dataCompiler = new DataCompiler($this);
        $this->associationBuilder = new AssociationBuilder($this);
        $this->eventCompiler = new EventCollector($this, $this->getRootTableRegistryName());
    }

    /**
     * Table Registry the factory is building entities from
     * @return string
     */
    abstract protected function getRootTableRegistryName(): string;

    /**
     * @return void
     */
    abstract protected function setDefaultTemplate(): void;

    /**
     * @param array|callable|null|int $makeParameter
     * @param int                     $times
     * @return static
     */
    public static function make($makeParameter = [], int $times = 1): BaseFactory
    {
        if (is_numeric($makeParameter)) {
            $factory = static::makeFromArray();
            $times = $makeParameter;
        } elseif (is_null($makeParameter)) {
            $factory = static::makeFromArray();
        } elseif (is_array($makeParameter)) {
            $factory = static::makeFromArray($makeParameter);
        } elseif (is_callable($makeParameter)) {
            $factory = static::makeFromCallable($makeParameter);
        } else {
            throw new InvalidArgumentException("make only accepts an array, an integer or a callable as the first parameter");
        }

        $factory->setUp($factory, $times);
        return $factory;
    }

    /**
     * Collect the number of entities to be created
     * Apply the default template in the factory
     * @param BaseFactory $factory
     * @param int         $times
     */
    private function setUp(BaseFactory $factory, int $times)
    {
        $factory->setTimes($times);
        $factory->setDefaultTemplate();
        $factory->getDataCompiler()->collectAssociationsFromDefaultTemplate();
    }

    /**
     * Method to apply all model event listeners, both in the
     * related TableRegistry as well as in the Behaviors
     * This is vey bad practice. The main purpose of the factory is to
     * generate data as fast and transparently as possible.
     * @deprecated Use instead $this->listeningToBehaviors and $this->listeningToModelEvents
     * @param array|callable|null|int $makeParameter
     * @param int                     $times
     * @return static
     */
    public static function makeWithModelEvents($makeParameter = [], $times = 1): BaseFactory
    {
        $factory = static::make($makeParameter, $times);
        $factory->withModelEvents = true;
        return $factory;
    }

    /**
     * @param array $data
     * @return static
     */
    private static function makeFromArray(array $data = []): BaseFactory
    {
        $factory = new static();
        $factory->getDataCompiler()->collectFromArray($data);
        return $factory;
    }

    /**
     * @param callable $fn
     * @return static
     */
    private static function makeFromCallable(callable $fn): BaseFactory
    {
        $factory = new static();
        $factory->getDataCompiler()->collectArrayFromCallable($fn);
        return $factory;
    }

    /**
     * @return Generator
     */
    public function getFaker(): Generator
    {
        if (is_null(self::$faker)) {
            $faker = Factory::create();
            $faker->seed(1234);
            self::$faker = $faker;
        }

        return self::$faker;
    }

    /**
     * Produce one entity from the present factory
     * @return EntityInterface
     */
    public function getEntity(): EntityInterface
    {
        return $this->getTable()->newEntity(
            $this->toArray()[0],
            $this->getMarshallerOptions()
        );
    }

    /**
     * Produce a set of entities from the present factory
     * @return EntityInterface[]
     */
    public function getEntities(): array
    {
        return $this->getTable()->newEntities(
            $this->toArray(),
            $this->getMarshallerOptions()
        );
    }

    /**
     * @return array
     */
    public function getMarshallerOptions(): array
    {
        $associated = $this->getAssociationBuilder()->getAssociated();
        if (!empty($associated)) {
            return array_merge($this->marshallerOptions, [
                'associated' => $this->getAssociationBuilder()->getAssociated()
            ]);
        } else {
            return $this->marshallerOptions;
        }
    }

    /**
     * @return array
     */
    public function getAssociated(): array
    {
        return $this->getAssociationBuilder()->getAssociated();
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        $data = [];
        for ($i = 0; $i < $this->times; $i++) {
            $compiledData = $this->getDataCompiler()->getCompiledTemplateData();
            if (isset($compiledData[0])) {
                $data = array_merge($data, $compiledData);
            } else {
                $data[] = $compiledData;
            }
        }

        return $data;
    }

    /**
     * The table on which the factories are build, the package's one
     * @return Table
     */
    public function getTable(): Table
    {
        if ($this->withModelEvents) {
            return $this->getRootTableRegistry();
        } else {
            return $this->getEventCompiler()->getTable();
        }
    }

    /**
     * The default table registry, the CakePHP one
     * @return Table
     */
    public function getRootTableRegistry(): Table
    {
        return TableRegistry::getTableLocator()->get($this->getRootTableRegistryName());
    }

    /**
     * @return array|EntityInterface|EntityInterface[]|ResultSetInterface|false|null
     * @throws Exception
     */
    public function persist()
    {
        $this->getDataCompiler()->startPersistMode();
        $data = $this->toArray();
        $this->getDataCompiler()->endPersistMode();

        try {
            if (count($data) === 1) {
                return $this->persistOne($data[0]);
            } else {
                return $this->persistMany($data);
            }
        } catch (Exception $exception) {
            $factory = get_class($this);
            $message = $exception->getMessage();
            throw new PersistenceException("Error in Factory $factory.\n Message: $message \n");
        }
    }

    /**
     * @param array $data
     * @return EntityInterface
     * @throws PersistenceFailedException When the entity couldn't be saved
     */
    protected function persistOne(array $data)
    {
        $TableRegistry = $this->getTable();
        $entity = $TableRegistry->newEntity($data, $this->getMarshallerOptions());
        return $TableRegistry->saveOrFail($entity, $this->getSaveOptions());
    }

    /**
     * @return array
     */
    private function getSaveOptions(): array
    {
        return array_merge($this->saveOptions, [
            'associated' => $this->getAssociated()
        ]);
    }

    /**
     * @param array $data
     *
     * @return EntityInterface[]|ResultSetInterface|false False on failure, entities list on success.
     * @throws Exception
     */
    protected function persistMany(array $data)
    {
        $TableRegistry = $this->getTable();
        $entities = $TableRegistry->newEntities($data, $this->getMarshallerOptions());
        return $TableRegistry->saveMany($entities, $this->getSaveOptions());
    }

    /**
     * Assigns the values of $data to the $keys of the entities generated
     * @param array $data
     * @return $this
     */
    public function patchData(array $data): self
    {
        $this->getDataCompiler()->collectFromPatch($data);
        return $this;
    }

    /**
     * A protected class dedicated to generating / collecting data for this factory
     * @return DataCompiler
     */
    protected function getDataCompiler(): DataCompiler
    {
        return $this->dataCompiler;
    }

    /**
     * A protected class dedicated to building / collecting associations for this factory
     * @return AssociationBuilder
     */
    protected function getAssociationBuilder(): AssociationBuilder
    {
        return $this->associationBuilder;
    }

    /**
     * A protected class to manage the Model Events inhrent to the creation of fixtures
     *
     * @return EventCollector
     */
    protected function getEventCompiler(): EventCollector
    {
        return $this->eventCompiler;
    }

    /**
     * Get the amount of entities generated by the factory
     * @return int
     */
    public function getTimes(): int
    {
        return $this->times;
    }

    /**
     * Set the amount of entities generated by the factory
     * @param int $times
     */
    public function setTimes(int $times): self
    {
        $this->times = $times;

        return $this;
    }

    /**
     * @param array|string $activeBehaviors
     */
    public function listeningToBehaviors($activeBehaviors)
    {
        $this->getEventCompiler()->listeningToBehaviors($activeBehaviors);
        return $this;
    }

    /**
     * @param array|string $activeModelEvents
     */
    public function listeningToModelEvents($activeModelEvents)
    {
        $this->getEventCompiler()->listeningToModelEvents($activeModelEvents);
        return $this;
    }

    /**
     * Set an offset for the Ids of the entities
     * persisted by this factory. This can be an array of type
     * [
     *      composite_key_1 => value1,
     *      composite_key_2 => value2,
     *      ...
     * ]
     * If not set, the offset is set randomly
     *
     * @param int|string|array $primaryKeyOffset
     *
     * @return self
     */
    public function setPrimaryKeyOffset($primaryKeyOffset): self
    {
        $this->getDataCompiler()->setPrimaryKeyOffset($primaryKeyOffset);
        return $this;
    }

    /**
     * Populate the entity factored
     * @param callable $fn
     * @return $this
     */
    protected function setDefaultData(callable $fn): self
    {
        $this->getDataCompiler()->collectFromDefaultTemplate($fn);
        return $this;
    }

    /**
     * Add associated entities to the fixtures generated by the factory
     * The associated name can be of several level, dot separated
     * The data can be an array, an integer, a callable or a factory
     * @param string $associationName
     * @param array|int|callable|BaseFactory $data
     * @return $this
     */
    public function with(string $associationName, $data = []): self
    {
        $this->getAssociationBuilder()->getAssociation($associationName);

        if (strpos($associationName, '.') === false && $data instanceof BaseFactory) {
            $factory = $data;
        } else {
            $factory = $this->getAssociationBuilder()->getAssociatedFactory($associationName, $data);
        }

        // Extract the first Association in the string
        $associationName = strtok($associationName, '.');

        // Remove the brackets in the association
        $associationName = $this->getAssociationBuilder()->removeBrackets($associationName);

        $this->getAssociationBuilder()->processToOneAssociation($associationName, $factory);

        $this->getDataCompiler()->collectAssociation($associationName, $factory);

        $this->getAssociationBuilder()->collectAssociatedFactory($associationName, $factory);

        return $this;
    }

    /**
     * Unset a previously associated factory
     * Useful to unrule associations set in setDefaultTemplate
     * @param string $association
     * @return $this
     */
    public function without(string $association): self
    {
        $this->getDataCompiler()->dropAssociation($association);
        $this->getAssociationBuilder()->dropAssociation($association);
        return $this;
    }

    /**
     * @param array $data
     * @return $this
     */
    public function mergeAssociated(array $data): self
    {
        $this->getAssociationBuilder()->setAssociated(
            array_merge(
                $this->getAssociationBuilder()->getAssociated(),
                $data
            )
        );

        return $this;
    }
}
