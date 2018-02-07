# sugar-module-packager

Require the package within your module's folder by executing: `composer require esimonetti/sugar-module-packager 0.1.0`

Your gitignore should look something like:
```
composer.lock
/vendor/
/pkg/
/releases/
```

Once done, to run the packager execute: `./vendor/bin/package <version number>`

A practical example is: `./vendor/bin/package 1.6`
