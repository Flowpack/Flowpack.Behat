# Installation

This package provides 2 commands to ease the setup of Behat in your Flow project:


**behat:setup**

This command will add Behat to the "Build/Behat" folder, install a binary to
"bin/behat" and download a current version of the selenium server to "bin/selenium-server.jar"

```
./flow behat:kickstart
```

**behat:kickstart**

This command will add a Tests/Behavior folder with basic Behat tests to the specified package

```
./flow behat:kickstart My.Package http://my.host/
```

After the setup and kickstart of Behat tests in a package you can execute the tests with this command:

```
bin/behat -c Packages/Application/My.Package/Tests/Behavior/behat.yml
```
