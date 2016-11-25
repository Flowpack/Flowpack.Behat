<?php
namespace Flowpack\Behat\Tests\Behat;

/*                                                                        *
 * This script belongs to the TYPO3 Flow package "Flowpack.Behat".        *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU Lesser General Public License, either version 3   *
 * of the License, or (at your option) any later version.                 *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

use Behat\Behat\Context\BehatContext;
use Behat\Behat\Exception\ErrorException;
use Doctrine\Common\Persistence\ObjectManager as DoctrineObjectManager;
use Doctrine\DBAL\DBALException;
use Doctrine\ORM\EntityManager;
use Flowpack\Behat\Tests\Functional\Aop\ConsoleLoggingCaptureAspect;
use Flowpack\Behat\Tests\Functional\Fixture\FixtureFactory;
use TYPO3\Flow\Cli\RequestBuilder;
use TYPO3\Flow\Cli\Response;
use TYPO3\Flow\Core\Booting\Scripts;
use TYPO3\Flow\Core\Bootstrap;
use TYPO3\Flow\Configuration\ConfigurationManager;
use TYPO3\Flow\Mvc\Dispatcher;
use TYPO3\Flow\Mvc\Routing\Router;
use TYPO3\Flow\Object\ObjectManagerInterface;
use TYPO3\Flow\Persistence\Doctrine\Service;
use TYPO3\Flow\Persistence\PersistenceManagerInterface;
use TYPO3\Flow\Reflection\ReflectionService;
use TYPO3\Flow\Security\Policy\PolicyService;
use TYPO3\Flow\Security\Policy\RoleRepository;

class FlowContext extends BehatContext {

	/**
	 * @var Bootstrap
	 */
	static protected $bootstrap;

	/**
	 * @var Router
	 */
	protected $router;

	/**
	 * @var ObjectManagerInterface
	 */
	protected $objectManager;

	/**
	 * @var \Doctrine\DBAL\Schema\Schema
	 */
	protected static $databaseSchema;

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
			class_exists(ErrorException::class);
		}
		$bootstrap = new Bootstrap('Testing/Behat');
		Scripts::initializeClassLoader($bootstrap);
		Scripts::initializeSignalSlot($bootstrap);
		Scripts::initializePackageManagement($bootstrap);
		$bootstrap->buildRuntimeSequence()->invoke($bootstrap);

		return $bootstrap;
	}

	/**
	 * @AfterSuite
	 */
	public static function shutdownFlow() {
		if (self::$bootstrap !== NULL) {
			self::$bootstrap->shutdown('Runtime');
		}
	}

	/**
	 * @When /^(?:|I )run the command "([^"]*)"$/
	 */
	public function iRunTheCommand($command) {
		$captureAspect = $this->objectManager->get(ConsoleLoggingCaptureAspect::class);
		$captureAspect->reset();

		$captureAspect->disableOutput();

		try {
			$request = $this->objectManager->get(RequestBuilder::class)->build($command);
			$response = new Response();

			$dispatcher = $this->objectManager->get(Dispatcher::class);
			$dispatcher->dispatch($request, $response);

			$this->lastCommandOutput = $captureAspect->getCapturedOutput();

			$this->persistAll();

			$captureAspect->enableOutput();
		} catch(\Exception $e) {
			$captureAspect->enableOutput();
		}
	}

	/**
	 * @Then /^(?:|I )should see the command output "([^"]*)"$/
	 */
	public function iShouldSeeTheCommandOutput($line) {
		\PHPUnit_Framework_Assert::assertContains($line, explode(PHP_EOL, $this->lastCommandOutput));
	}

	/**
	 * @Then /^(P|p)rint last command output$/
	 */
	public function printLastCommandOutput() {
		$this->printDebug($this->lastCommandOutput);
	}

	/**
	 * @Then /^(?:|I )should see "([^"]*)" in the command output$/
	 */
	public function iShouldSeeSomethingInTheCommandOutput($contents) {
		\PHPUnit_Framework_Assert::assertContains($contents, $this->lastCommandOutput);
	}

	/**
	 * @BeforeScenario @fixtures
	 */
	public function resetTestFixtures($event) {
		/** @var EntityManager $entityManager */
		$entityManager = $this->objectManager->get(DoctrineObjectManager::class);
		$entityManager->clear();

		if (self::$databaseSchema !== NULL) {
			$this->truncateTables($entityManager);
		} else {
			try {
				/** @var Service $doctrineService */
				$doctrineService = $this->objectManager->get(Service::class);
				$doctrineService->executeMigrations();
				$needsTruncate = TRUE;
			} catch (DBALException $exception) {
				// Do an initial teardown to drop the schema cleanly
				$this->objectManager->get(PersistenceManagerInterface::class)->tearDown();

				/** @var Service $doctrineService */
				$doctrineService = $this->objectManager->get(Service::class);
				$doctrineService->executeMigrations();
				$needsTruncate = FALSE;
			}

			$schema = $entityManager->getConnection()->getSchemaManager()->createSchema();
			self::$databaseSchema = $schema;

			if ($needsTruncate) {
				$this->truncateTables($entityManager);
			}

				// FIXME Check if this is needed at all!
			$proxyFactory = $entityManager->getProxyFactory();
			$proxyFactory->generateProxyClasses($entityManager->getMetadataFactory()->getAllMetadata());
		}

		$this->resetFactories();
	}

	/**
	 * Truncate all known tables
	 *
	 * @param EntityManager $entityManager
	 * @return void
	 */
	public function truncateTables($entityManager) {
		$connection = $entityManager->getConnection();

		$tables = array_filter(self::$databaseSchema->getTables(), function($table) {
			return $table->getName() !== 'flow_doctrine_migrationstatus';
		});
		switch ($connection->getDatabasePlatform()->getName()) {
			case 'mysql':
				$sql = 'SET FOREIGN_KEY_CHECKS=0;';
				foreach ($tables as $table) {
					$sql .= 'TRUNCATE `' . $table->getName() . '`;';
				}
				$sql .= 'SET FOREIGN_KEY_CHECKS=1;';
				$connection->executeQuery($sql);
				break;
			case 'postgresql':
			default:
				foreach ($tables as $table) {
					$sql = 'TRUNCATE ' . $table->getName() . ' CASCADE;';
					$connection->executeQuery($sql);
				}
				break;
		}
	}

	/**
	 * Reset factory instances
	 *
	 * Must be called after all persistAll calls and before scenarios to have a clean state.
	 *
	 * @return void
	 */
	protected function resetFactories() {
		/** @var $reflectionService ReflectionService */
		$reflectionService = $this->objectManager->get(ReflectionService::class);
		$fixtureFactoryClassNames = $reflectionService->getAllSubClassNamesForClass(FixtureFactory::class);
		foreach ($fixtureFactoryClassNames as $fixtureFactoyClassName) {
			if (!$reflectionService->isClassAbstract($fixtureFactoyClassName)) {
				$factory = $this->objectManager->get($fixtureFactoyClassName);
				$factory->reset();
			}
		}

		$this->resetRolesAndPolicyService();
	}

	/**
	 * Reset policy service and role repository
	 *
	 * This is needed to remove cached role entities after resetting the database.
	 *
	 * @return void
	 */
	protected function resetRolesAndPolicyService() {
		$this->objectManager->get(PolicyService::class)->reset();

		if ($this->objectManager->isRegistered(RoleRepository::class)) {
			$roleRepository = $this->objectManager->get(RoleRepository::class);
			\TYPO3\Flow\Reflection\ObjectAccess::setProperty($roleRepository, 'newRoles', array(), TRUE);
		}
	}

	/**
	 * Persist any changes
	 */
	public function persistAll() {
		$this->objectManager->get(PersistenceManagerInterface::class)->persistAll();
		$this->objectManager->get(PersistenceManagerInterface::class)->clearState();

		$this->resetFactories();
	}

	/**
	 * @return Router
	 */
	protected function getRouter() {
		if ($this->router === NULL) {
			$this->router = $this->objectManager->get(Router::class);

			$configurationManager = $this->objectManager->get(ConfigurationManager::class);
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
	 * @deprecated Use resolvePageUri
	 */
	public function resolvePath($pageName) {
		return $this->resolvePageUri($pageName);
	}

	/**
	 * Resolves a URI for the given page name
	 *
	 * If a Flow route with a name equal to $pageName exists it will be resolved.
	 * An absolute path will be used as is for compatibility with the default MinkContext.
	 *
	 * @param string $pageName
	 * @param array $arguments
	 * @return string
	 * @throws \InvalidArgumentException
	 */
	public function resolvePageUri($pageName, array $arguments = NULL) {
		$uri = NULL;
		if (strpos($pageName, '/') === 0) {
			$uri = $pageName;
			return $uri;
		} else {
			$router = $this->getRouter();

			/** @var \TYPO3\Flow\Mvc\Routing\Route $route */
			foreach ($router->getRoutes() as $route) {
				if (preg_match('/::\s*' . preg_quote($pageName, '/') . '$/', $route->getName())) {
					$routeValues = $route->getDefaults();
					if (is_array($arguments)) {
						$routeValues = array_merge($routeValues, $arguments);
					}
					if ($route->resolves($routeValues)) {
						$uri = $route->getResolvedUriPath();
						break;
					}
				}
			}
			if ($uri === NULL) {
				throw new \InvalidArgumentException('Could not resolve a route for name "' . $pageName . '"');
			}
			if (strpos($uri, 'http') !== 0 && strpos($uri, '/') !== 0) {
				$uri = '/' . $uri;
			}
			return $uri;
		}
	}

	/**
	 * @return ObjectManagerInterface
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
