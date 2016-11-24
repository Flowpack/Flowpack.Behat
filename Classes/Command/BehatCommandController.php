<?php
namespace Neos\Behat\Command;

/*
 * This file is part of the Neos.Behat package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Http\Client\Browser;
use Neos\Flow\Http\Client\CurlEngine;
use Neos\Utility\Files;
use Neos\Flow\Cli\CommandController;

/**
 * @Flow\Scope("singleton")
 */
class BehatCommandController extends CommandController
{

    /**
     * @var \Neos\Flow\Package\PackageManagerInterface
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
    public function setupCommand()
    {
        $behatBuildPath = FLOW_PATH_ROOT . 'Build/Behat/';
        if (!is_dir($behatBuildPath)) {
            Files::copyDirectoryRecursively('resource://Neos.Behat/Private/Build/Behat', $behatBuildPath);
        }

        $behatBinaryPath = FLOW_PATH_ROOT . 'bin/behat';
        if (!is_file($behatBinaryPath)) {
            system('cd "' . $behatBuildPath . '" && composer install');
            $this->outputLine();
            $this->outputLine('Installed Behat to bin/behat');
        }

        $seleniumBinaryPath = FLOW_PATH_ROOT . 'bin/selenium-server.jar';
        if (!is_file($seleniumBinaryPath)) {
            $seleniumVersion = 'selenium-server-standalone-2.53.1.jar';
            $seleniumUrl = 'http://selenium-release.storage.googleapis.com/2.53/' . $seleniumVersion;
            $returnValue = 0;
            system('wget --quiet ' . $seleniumUrl, $returnValue);
            $this->outputLine('Downloading Selenium ' . $seleniumVersion . ' to bin/selenium-server.jar...');
            if ($returnValue > 0) {
                throw new \RuntimeException('Could not download selenium from ' . $seleniumUrl . '. wget errno: ' . $returnValue);
            }
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
    public function kickstartCommand($packageName, $host)
    {
        $this->setupCommand();

        if ($this->packageManager->isPackageAvailable($packageName)) {
            $package = $this->packageManager->getPackage($packageName);

            $behaviorTestsPath = $package->getPackagePath() . 'Tests/Behavior';
            if (!is_dir($behaviorTestsPath)) {
                Files::copyDirectoryRecursively('resource://Neos.Behat/Private/Tests/Behavior', $behaviorTestsPath);
            }

            $behatConfiguration = file_get_contents($behaviorTestsPath . '/behat.yml.dist');
            $behatConfiguration = str_replace('base_url: http://localhost/', 'base_url: ' . $host, $behatConfiguration);
            file_put_contents($behaviorTestsPath . '/behat.yml', $behatConfiguration);
        }
        $this->outputLine('Behat is installed and can be used by running: "bin/behat -c Packages/Application/%s/Tests/Behavior/behat.yml"', array($packageName));
    }

}
