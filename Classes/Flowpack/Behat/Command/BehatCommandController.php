<?php
namespace Flowpack\Behat\Command;

/*                                                                        *
 * This script belongs to the TYPO3 Flow package "Flowpack.Behat".        *
 *                                                                        *
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
			$this->outputLine('Installed Behat to: bin/behat. You can execute it through: "bin/behat -c Packages/Application/TYPO3.Neos/Tests/Behavior/behat.yml"');
		}

		$seleniumBinaryPath = FLOW_PATH_ROOT . 'bin/selenium-server.jar';
		if (!is_file($seleniumBinaryPath)) {
			$seleniumVersion = 'selenium-server-standalone-2.35.0.jar';
			system('wget --quiet http://selenium.googlecode.com/files/' . $seleniumVersion);
			rename(FLOW_PATH_ROOT . $seleniumVersion, FLOW_PATH_ROOT . 'bin/selenium-server.jar');
			$this->outputLine('Downloaded Selenium to bin/selenium-server.jar. you can execute it through: "java -jar selenium-server.jar"');
		}
	}

	/**
	 * This command will help you to kickstart the testing in a package
	 *
	 * It will add a folder Behavior in your Package with the default
	 * behat setup
	 *
	 * @param string $packageName
	 * @param string $host
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

?>