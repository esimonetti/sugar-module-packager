# sugar-module-packager

Require the package within your module's package directory by executing: `composer require esimonetti/sugar-module-packager 0.1.0`

Your gitignore should look something like the following:
```
composer.lock
/vendor/
/pkg/
/releases/
```

Once your package's code is ready to be delivered, to run the packager execute: `./vendor/bin/package <version number>` ie: `./vendor/bin/package 1.6`

## Example
A simple code example of this library can be found here: https://github.com/esimonetti/SugarModulePackagerSample

The `src` directory contains all the code that will be copied into the Sugar instance according to their relative path within `src`.
The `configuration` directory defines the manifest information. If required it defines the installdefs actions. On the `configuration` directory it is possible to define the code templating actions to be completed by the packager as well.
