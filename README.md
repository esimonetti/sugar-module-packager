# Sugar Module Packager

There are two options to start using the Sugar Module Packager:

* Start from the pre-made template (with or without Docker)
* Start from scratch by requiring the composer library (with or without Docker)

## Docker
If you have Docker installed, you can conveniently run the following commands

### New package - Start from pre-made template
```
mkdir my-module
cd my-module
docker run -it -v ${PWD}:/usr/src/packager -w /usr/src/packager esimonetti/sugarmodulepackager template
```

### New package - Start from scratch
```
mkdir my-module
cd my-module
docker run -it -v ${PWD}:/usr/src/packager -w /usr/src/packager esimonetti/sugarmodulepackager new
```

### Packaging of code
After the Sugar Module Packager has been installed successfully, it is possible to package your code (through Docker) by executing: `./vendor/bin/package-docker <version number>` ie: `./vendor/bin/package-docker 1.6`


## Manual Approach
Alternatively to Docker, it is possible to proceed with the manual approach

### New package - Start from pre-made template
Visit https://github.com/esimonetti/SugarTemplateModule and follow the instructions

### New package - Start from scratch
Require the composer library within your module's source directory by executing: `composer require esimonetti/sugar-module-packager 0.2.3`

The .gitignore should look like the following:
```
composer.lock
/vendor/
/pkg/
/releases/
```

### Packaging of code
After the Sugar Module Packager has been installed successfully, it is possible to package your code (without Docker) by executing: `./vendor/bin/package <version number>` ie: `./vendor/bin/package 1.6`

## Additional Example
A simple code example on how to leverage this library can be found on: https://github.com/esimonetti/SugarModulePackagerSample

## Packager Details
* The `src` directory contains the code that should be copied into the Sugar instance according to their relative path within `src`
    * There are a couple of exceptions: `LICENSE` and `README.txt` files will not be copied into the instance, just into the installable package
* `configuration` directory
    * Must define the `manifest` information on `manifest.php`
    * Optionally, it is possible to define the `installdefs` actions on `installdefs.php`
    * Optionally, for more complex use-cases, it is possible to define code templating actions to be completed by the packager across multiple modules, within the `templates.php` file
        * It is possible to define multiple template sections based on multiple template actions and patterns to replicate across modules
            * The array keys of the templates configuration array define the package directories to read files from, when generating the output
            * The array content defines the `directory_pattern` tree prefix, that will be appendedd as a prefix of every file path of the local directory
                * The string `{MODULENAME}` within the `directory_pattern` templates configuration option, will be replaced with the current module name during package generation
            * The array content defines the `modules` list that must contain the module names as array keys and the object name as array values
                * The string `{MODULENAME}` within your local directory template files, will be replaced during package generation as the configuration's module list array key (the module name, usually plural eg: `Accounts`)
                * The string `{OBJECTNAME}` within your local directory template files, will be replaced during package generation as the configuration's module list array value (the object name of the module, usually singular eg: `Account`)
