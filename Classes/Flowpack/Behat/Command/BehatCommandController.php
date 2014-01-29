<?php
namespace Flowpack\Behat\Command;

/*                                                                        *
 * This script belongs to the TYPO3 Flow package "Flowpack.Behat".        *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU Lesser General Public License, either version 3   *
 * of the License, or (at your option) any later version.                 *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Http\Client\Browser;
use TYPO3\Flow\Http\Client\CurlEngine;
use TYPO3\Flow\Utility\Files;

/**
 * @Flow\Scope("singleton")
 */
class BehatCommandController extends \TYPO3\Flow\Cli\CommandController {

	/**
	 * @var \TYPO3\Flow\Package\PackageManagerInterface
	 * @Flow\Inject
	 */
	protected $packageManager;

	/**
	 * This command will help you to install Behat
	 *
	 * It will check for all necessary things to run Behat tests.
	 * If you specify a Package name it will create the basis for your new
	 * test.
	 *
	 * @return void
	 */
	public function setupCommand() {
		$behatBuildPath = FLOW_PATH_ROOT . 'Build/Behat/';
		if (!is_dir($behatBuildPath)) {
			Files::copyDirectoryRecursively('resource://Flowpack.Behat/Private/Build/Behat', $behatBuildPath);
		}

		$behatBinaryPath = FLOW_PATH_ROOT . 'bin/behat';
		if (!is_file($behatBinaryPath)) {
			system('cd ' . $behatBuildPath . ' && composer install');
			$this->outputLine();
			$this->outputLine('Installed Behat to bin/behat');
		}

		$seleniumBinaryPath = FLOW_PATH_ROOT . 'bin/selenium-server.jar';
		if (!is_file($seleniumBinaryPath)) {
			$seleniumVersion = 'selenium-server-standalone-2.39.0.jar';
			system('wget --quiet http://selenium.googlecode.com/files/' . $seleniumVersion);
			rename(FLOW_PATH_ROOT . $seleniumVersion, FLOW_PATH_ROOT . 'bin/selenium-server.jar');
			$this->outputLine('Downloaded Selenium to bin/selenium-server.jar');
			$this->outputLine('You can execute it through: "java -jar selenium-server.jar"');
		}
	}

	/**
	 * This command will help you to kickstart Behat testing for a package
	 *
	 * It will add a folder Tests/Behavior in your package with a default Behat setup.
	 *
	 * @param string $packageName The package key
	 * @param string $host The base URL for the Flow application (e.g. http://example.local/)
	 * @return void
	 */
	public function kickstartCommand($packageName, $host) {
		$this->setupCommand();

		if ($this->packageManager->isPackageAvailable($packageName)) {
			$package = $this->packageManager->getPackage($packageName);

			$behaviorTestsPath = $package->getPackagePath() . 'Tests/Behavior';
			if (!is_dir($behaviorTestsPath)) {
				Files::copyDirectoryRecursively('resource://Flowpack.Behat/Private/Tests/Behavior', $behaviorTestsPath);
			}

			$behatConfiguration = file_get_contents($behaviorTestsPath . '/behat.yml.dist');
			$behatConfiguration = str_replace('base_url: http://localhost/', 'base_url: ' . $host, $behatConfiguration);
			file_put_contents($behaviorTestsPath . '/behat.yml', $behatConfiguration);
		}
		$this->outputLine('Behat is installed and can be used by running: "bin/behat -c Packages/Application/%s/Tests/Behavior/behat.yml"', array($packageName));
	}

}
