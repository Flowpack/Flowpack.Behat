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
use TYPO3\Flow\Exception;
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
			$seleniumVersion = 'selenium-server-standalone-2.35.0.jar';
			system('wget --quiet http://selenium.googlecode.com/files/' . $seleniumVersion);
			rename(FLOW_PATH_ROOT . $seleniumVersion, FLOW_PATH_ROOT . 'bin/selenium-server.jar');
			$this->outputLine('Downloaded Selenium to bin/selenium-server.jar');
			$this->outputLine('You can execute it through: "java -jar selenium-server.jar"');
		}
	}

	/**
	 * This command will show all the available definitions
	 *
	 * @param string $packageKey
	 * @param string $needle
	 * @param boolean $extended
	 */
	public function definitionsCommand($packageKey, $needle = NULL, $extended = FALSE) {
		$command = $this->prepareCommand($packageKey);
		if ($needle !== NULL) {
			$command .= sprintf(' -d "%s"', $needle);
		} else {
			$command .= $extended ? ' -di' : ' -dl';
		}

		$this->executeCommand($command);
	}

	/**
	 * This command will run the test suite for the given package
	 *
	 * @param string $packageKey The package key
	 * @param string $profile Specify config profile to use.
	 * @param string $format How to format features. pretty is default.
	 * @param string $output Write formatter output to a file/directory instead of STDOUT
	 * @param string $feature The feature file or directory
	 * @param boolean $strict Fail if there are any undefined or pending steps.
	 * @param boolean $dryRun Invokes formatters without executing the steps & hooks.
	 */
	public function runCommand($packageKey, $profile = NULL, $format = 'pretty', $output = NULL, $feature = NULL, $strict = FALSE, $dryRun = FALSE) {
		$command = $this->prepareCommand($packageKey, $profile, $strict, $dryRun);
		$package = $this->packageManager->getPackage($packageKey);

		$command .= ' --format ' . $format;
		if ($output !== NULL) {
			$command .= ' --output ' . $output;
		}
		if ($feature !== NULL) {
			$featurePath = $package->getPackagePath() . '/Tests/Behavior/Features/' . $feature;
			if (!@is_file($featurePath)) {
				$this->outputLine('Feature file or directory is missing ...');
				$this->quit(1);
			}
			$command .= ' ' . $package->getPackagePath() . '/Tests/Behavior/Features/' . $feature;
		}

		$this->executeCommand($command);
	}

	/**
	 * This command will help you to kickstart Behat testing for a package
	 *
	 * It will add a folder Tests/Behavior in your package with a default Behat setup.
	 *
	 * @param string $packageKey The package key
	 * @param string $host The base URL for the Flow application (e.g. http://example.local/)
	 * @return void
	 */
	public function kickstartCommand($packageKey, $host) {
		$this->setupCommand();

		if ($this->packageManager->isPackageAvailable($packageKey)) {
			$package = $this->packageManager->getPackage($packageKey);

			$behaviorTestsPath = $package->getPackagePath() . 'Tests/Behavior';
			if (!is_dir($behaviorTestsPath)) {
				Files::copyDirectoryRecursively('resource://Flowpack.Behat/Private/Tests/Behavior', $behaviorTestsPath);
			}

			$behatConfiguration = file_get_contents($behaviorTestsPath . '/behat.yml.dist');
			$behatConfiguration = str_replace('base_url: http://localhost/', 'base_url: ' . $host, $behatConfiguration);
			file_put_contents($behaviorTestsPath . '/behat.yml', $behatConfiguration);
		}
		$this->outputLine('Behat is installed and can be used by running: "bin/behat -c Packages/Application/%s/Tests/Behavior/behat.yml"', array($packageKey));
	}

	/**
	 * @param string $command
	 */
	protected function executeCommand($command) {
		system($command, $returnCode);

		$this->outputLine("\n\n");
		if ($returnCode == 0) {
			$this->outputFormatted('<b>Behat command executed with success</b>');
		} else {
			$this->outputFormatted('<em>Behat command falied with return code: %s</em>', array($returnCode));
		}
		$this->quit($returnCode);
	}

	/**
	 * @param string $packageKey
	 * @param string $profile
	 * @param boolean $strict
	 * @param boolean $dryRun
	 * @return string
	 * @throws \TYPO3\Flow\Exception
	 */
	protected function prepareCommand($packageKey, $profile = NULL, $strict = FALSE, $dryRun = FALSE) {
		if (!$this->packageManager->isPackageAvailable($packageKey)) {
			throw new Exception('This package is not available.', 1382980897);
		}
		system('cd ' . FLOW_PATH_ROOT);

		$command = './bin/behat';
		if (!@is_file($command)) {
			$this->outputLine('Behat binary is missing, you can install it by executing: ./flow behat:setup');
			$this->quit(1);
		}
		$package = $this->packageManager->getPackage($packageKey);
		$command .= sprintf(' -c %s/Tests/Behavior/behat.yml', $package->getPackagePath());
		if ($this->response->hasColorSupport()) {
			$command .= ' --ansi';
		}
		if ($profile) {
			$command .= ' --profile ' . $profile;
		}
		if ($strict) {
			$command .= ' --strict';
		}
		if ($dryRun) {
			$command .= ' --dry-run';
		}

		return $command;
	}

}
