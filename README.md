# sugar-module-packager

Require the package within your module's package directory by executing: `composer require esimonetti/sugar-module-packager 0.2.0`

Your gitignore should look something like the following:
```
composer.lock
/vendor/
/pkg/
/releases/
```

Once your package's code is ready to be delivered, to run the packager execute: `./vendor/bin/package <version number>` ie: `./vendor/bin/package 1.6`

## Example
A simple code example on how to leverage this library can be found here: https://github.com/esimonetti/SugarModulePackagerSample

### Explanation notes
* The `src` directory contains the code that should be copied into the Sugar instance according to their relative path within `src`
    * There are a couple of exceptions: `LICENSE` and `README.txt` files will not be copied into the instance, just into the installable package
* `configuration` directory
    * Must define the `manifest` information on `manifest.php`
    * Can define the `installdefs` actions on `installdefs.php`
    * Can define code templating actions to be completed by the packager, within the `templates.php` file
        * It is possible to define multiple template sections based on multiple template actions and patterns to replicate across modules
            * The array keys of the templates configuration array define the package directories to read files from, when generating the output
            * The array content defines the `directory_pattern` tree prefix, that will be appendedd as a prefix of every file path of the local directory
                * The string `{MODULENAME}` within the `directory_pattern` templates configuration option, will be replaced with the current module name during package generation
            * The array content defines the `modules` list that must contain the module names as array keys and the object name as array values
                * The string `{MODULENAME}` within your local directory template files, will be replaced during package generation as the configuration's module list array key (the module name, usually plural eg: `Accounts`)
                * The string `{OBJECTNAME}` within your local directory template files, will be replaced during package generation as the configuration's module list array value (the object name of the module, usually singular eg: `Account`)
