<?php
namespace Flowpack\Behat\Tests\Behat;

use Behat\Behat\Context\BehatContext;
use TYPO3\Flow\Core\Booting\Scripts,
	TYPO3\Flow\Core\Bootstrap,
	TYPO3\Flow\Configuration\ConfigurationManager;

class FlowContext extends BehatContext {

	/**
	 * @var Bootstrap
	 */
	static protected $bootstrap;

	/**
	 * @var \TYPO3\Flow\Mvc\Routing\Router
	 */
	protected $router;

	/**
	 * @var \TYPO3\Flow\Object\ObjectManagerInterface
	 */
	protected $objectManager;

	/**
	 * @var array
	 */
	static protected $createSchemaSql;

	/**
	 * @var string
	 */
	protected $lastCommandOutput;

	/**
	 * @param array $parameters
	 */
	public function __construct(array $parameters) {
		if (self::$bootstrap === NULL) {
			self::$bootstrap = $this->initializeFlow();
		}
		$this->objectManager = self::$bootstrap->getObjectManager();
	}

	/**
	 * Create a flow bootstrap instance
	 */
	protected function initializeFlow() {
		require_once(__DIR__ . '/../../../../Framework/TYPO3.Flow/Classes/TYPO3/Flow/Core/Bootstrap.php');
		if (!defined('FLOW_PATH_ROOT')) {
			define('FLOW_PATH_ROOT', realpath(__DIR__ . '/../../../../..') . '/');
		}
			// The new classloader needs warnings converted to exceptions
		if (!defined('BEHAT_ERROR_REPORTING')) {
			define('BEHAT_ERROR_REPORTING', E_ALL);
			// Load ErrorException class, since it will be used in the Behat error handler
			class_exists('Behat\Behat\Exception\ErrorException');
		}
		$bootstrap = new Bootstrap('Testing/Behat');
		Scripts::initializeClassLoader($bootstrap);
		Scripts::initializeSignalSlot($bootstrap);
		Scripts::initializePackageManagement($bootstrap);
		$bootstrap->buildRuntimeSequence()->invoke($bootstrap);

		return $bootstrap;
	}

	/**
     * @When /^I run the command "([^"]*)"$/
     */
    public function iRunTheCommand($command) {
		$this->lastCommandOutput = NULL;

        $request = $this->objectManager->get('TYPO3\Flow\Cli\RequestBuilder')->build($command);
		$response = new \TYPO3\Flow\Cli\Response();

		$dispatcher = $this->objectManager->get('TYPO3\Flow\Mvc\Dispatcher');
		$dispatcher->dispatch($request, $response);

		$this->lastCommandOutput = $response->getContent();

		$this->persistAll();
    }

	/**
     * @Then /^I should see the command output "([^"]*)"$/
     */
    public function iShouldGetTheOutput($line) {
		\PHPUnit_Framework_Assert::assertContains($line, explode(PHP_EOL, $this->lastCommandOutput));
    }

	/**
	 * @BeforeScenario @fixtures
	 *
	 * @param \Behat\Behat\Event\EventInterface $event
	 */
	public function resetTestFixtures($event) {
		$this->objectManager->get('TYPO3\Flow\Persistence\PersistenceManagerInterface')->tearDown();

		/** @var \Doctrine\ORM\EntityManager $em */
		$em = $this->objectManager->get('Doctrine\Common\Persistence\ObjectManager');
		if (self::$createSchemaSql !== NULL) {
			$conn = $em->getConnection();
			foreach (self::$createSchemaSql as $sql) {
				$conn->executeQuery($sql);
			}
		} else {
			/** @var \TYPO3\Flow\Persistence\Doctrine\Service $doctrineService */
			$doctrineService = $this->objectManager->get('TYPO3\Flow\Persistence\Doctrine\Service');
			$doctrineService->executeMigrations();

			$schema = $em->getConnection()->getSchemaManager()->createSchema();
			self::$createSchemaSql = $schema->toSql($em->getConnection()->getDatabasePlatform());

			// FIXME Check if this is needed at all!
			$proxyFactory = $em->getProxyFactory();
			$proxyFactory->generateProxyClasses($em->getMetadataFactory()->getAllMetadata());
		}

			// Reset roles from policy after resetting database
		$this->objectManager->get('TYPO3\Flow\Security\Policy\PolicyService')->reset();

		$this->resetRoleRepository();
		$this->resetFactories();
	}

	/**
	 * Reset factory instances
	 *
	 * Must be called after all persistAll calls and before scenarios to have a clean state.
	 *
	 * @return void
	 */
	protected function resetFactories() {
		/** @var $reflectionService \TYPO3\Flow\Reflection\ReflectionService */
		$reflectionService = $this->objectManager->get('TYPO3\Flow\Reflection\ReflectionService');
		$fixtureFactoryClassNames = $reflectionService->getAllSubClassNamesForClass('Flowpack\Behat\Tests\Functional\Fixture\FixtureFactory');
		foreach ($fixtureFactoryClassNames as $fixtureFactoyClassName) {
			$factory = $this->objectManager->get($fixtureFactoyClassName);
			$factory->reset();
		}
	}

	/**
	 * @return void
	 */
	protected function resetRoleRepository() {
		$roleRepository = $this->objectManager->get('TYPO3\Flow\Security\Policy\RoleRepository');
		\TYPO3\Flow\Reflection\ObjectAccess::setProperty($roleRepository, 'newRoles', array(), TRUE);
	}

	/**
	 * Persist any changes
	 */
	public function persistAll() {
		$this->objectManager->get('TYPO3\Flow\Persistence\PersistenceManagerInterface')->persistAll();
		$this->objectManager->get('TYPO3\Flow\Persistence\PersistenceManagerInterface')->clearState();

		$this->resetFactories();
		$this->resetRoleRepository();
	}

	/**
	 * @return \TYPO3\Flow\Mvc\Routing\Router
	 */
	protected function getRouter() {
		if ($this->router === NULL) {
			$this->router = $this->objectManager->get('\TYPO3\Flow\Mvc\Routing\Router');

			$configurationManager = $this->objectManager->get('TYPO3\Flow\Configuration\ConfigurationManager');
			$routesConfiguration = $configurationManager->getConfiguration(ConfigurationManager::CONFIGURATION_TYPE_ROUTES);
			$this->router->setRoutesConfiguration($routesConfiguration);
		}
		return $this->router;
	}

	/**
	 * Resolve a path by route name or a relative path (as a fallback)
	 *
	 * @param string $pageName
	 * @return string
	 */
	public function resolvePath($pageName) {
		$uri = NULL;
		if (strpos($pageName, '/') === 0) {
			$uri = $pageName;
			return $uri;
		} else {
			$router = $this->getRouter();

			/** @var \TYPO3\Flow\Mvc\Routing\Route $route */
			foreach ($router->getRoutes() as $route) {
				if (preg_match('/::\s*' . preg_quote($pageName, '/') . '$/i', $route->getName())) {
					$routeValues = $route->getDefaults();
					if ($route->resolves($routeValues)) {
						$uri = $route->getMatchingUri();
						break;
					}
				}
			}
			if ($uri === NULL) {
				\PHPUnit_Framework_Assert::fail('Could not resolve a route for name "' . $pageName . '"');
				return $uri;
			}
			return $uri;
		}
	}

	/**
	 * @return \TYPO3\Flow\Object\ObjectManagerInterface
	 */
	public function getObjectManager() {
		return $this->objectManager;
	}

	/**
	 * @return string
	 */
	public function getLastCommandOutput() {
		return $this->lastCommandOutput;
	}

}

?>