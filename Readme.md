# Project preparation

For running the Behat tests you will need some additional contexts. The 'Testing/Behat' context is required, the 'Development/Behat' is only required for running selenium tests.

* The context `Development/Behat` should be mounted as a separate virtual host and is used by Behat to
  do the actual HTTP requests.
* The context `Testing/Behat` is used inside the Behat feature context to set up test data and reset the
  database after each scenario.

These contexts should share the same database to work properly. Make sure to create a new database for the Behat tests
since all the data will be removed after each scenario.

## Example configuration

`FLOW_ROOT/Configuration/Development/Behat/Settings.yaml`::

	Neos:
	  Flow:
	    persistence:
	      backendOptions:
	        dbname: 'neos_testing_behat'

`FLOW_ROOT/Configuration/Testing/Behat/Settings.yaml`::

	Neos:
	  Flow:
	    persistence:
	      backendOptions:
	        dbname: 'neos_testing_behat'
	        driver: pdo_mysql
	        user: ''
	        password: ''

Example virtual host configuration for Apache::

	<VirtualHost *:80>
		DocumentRoot "FLOW_ROOT/Web"
		ServerName neos.behat.test
		SetEnv FLOW_CONTEXT Development/Behat
	</VirtualHost>
	

# Installation

This package provides 2 commands to ease the setup of Behat in your Flow project:

**behat:setup**

This command will add Behat to the "Build/Behat" folder, install a binary to
"bin/behat" and download a current version of the selenium server to "bin/selenium-server.jar".
For installing all composer packages this command depends on having "composer" installed in your PATH.
If you don't meet that requirement you should manually run a "composer install" in the "Build/Behat" folder.

```
./flow behat:setup
```

**behat:kickstart**

This command will add a Tests/Behavior folder with basic Behat tests to the specified package

```
./flow behat:kickstart --package-name <My.Package> --host <http://my.host/>
```

```
./flow behat:kickstart My.Package http://my.host/
```

After the setup and kickstart of Behat tests in a package you can execute the tests with this command:

```
bin/behat -c Packages/Application/My.Package/Tests/Behavior/behat.yml
```

*You might want to warmup the cache before you start the test. Otherwise the tests might fail due to a timeout.
You can do that with `FLOW_CONTEXT=Development/Behat ./flow flow:cache:warmup`.*

# Configuring Behat

We advise to ship a `behat.yml.dist` file in your package and put the `behat.yml` path in the ignore configuration
for your versioning system (for git this means the `.gitignore`). Doing so you can put a sane default configuration
in the `behat.yml.dist` file which a developer can use for running the tests. If the developer needs to change that
configuration he can make a local copy as `behat.yml`, change it at will and use it for running the tests.

To run the tests, Behat needs a base URI pointing to the special virtual host running with the `Development/Behat`
context. To set a custom base URI the default file should be copied and customized

	cd Packages/Application/Neos.Neos/Tests/Behavior
	cp behat.yml.dist behat.yml
	# Edit file behat.yml

Customized `behat.yml`

	default:
	  paths:
	    features: Features
	    bootstrap: %behat.paths.features%/Bootstrap
	  extensions:
	    Behat\MinkExtension\Extension:
	      files_path: features/Resources
	      show_cmd: 'open %s'
	      goutte: ~
	      selenium2: ~

	      base_url: http://neos.behat.test/

Selenium
--------

Some tests require a running Selenium server for testing browser advanced interaction and JavaScript.
Selenium Server can be downloaded at http://docs.seleniumhq.org/download/ and started with::

	java -jar selenium-server-standalone-2.x.0.jar

Debugging
---------

* Make sure to use a new database and configure the same databse for `Development/Behat` and `Testing/Behat`
* Run Behat with the `-v` option to get more information about errors and failed tests
* A failed step can be inspected by inserting "Then show last response" in the `.feature` definition
