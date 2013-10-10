# Installation

This package provides 2 Commands to ease the setup of behat in your Project:


**behat:setup**

This command will add Behat to the "Build/Behat" folder, install a binary to
"bin/behat" and download a current version of the selenium server to "bin/selenium-server.jar"

```
./flow behat:kickstart
```

**behat:kickstart**

This command will add a dummy Tests/Behavior folder to the specified package

```
./flow behat:kickstart My.Package http://my.host/
```

Then you can execute it through this command:

```
bin/behat -c Packages/Application/My.Package/Tests/Behavior/behat.yml
```