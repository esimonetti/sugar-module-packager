<?php

// Enrico Simonetti
// enricosimonetti.com
//
// 2018-01-25 on Sugar 7.9.3.0
//
// Tool that helps package Sugar installable modules

namespace SugarModulePackager;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use ZipArchive;

require_once('PackageOutput.php');

class PackageUtils
{
    protected static $release_directory = 'releases';
    protected static $prefix_release_package = 'module_';
    protected static $config_directory = 'configuration';
    protected static $config_template_file = 'templates.php';
    protected static $config_installdefs_file = 'installdefs.php';
    protected static $src_directory = 'src';
    protected static $pkg_directory = 'pkg';
    protected static $manifest_file = 'manifest.php';

    protected static $files_to_not_package = array('.DS_Store', '.gitkeep');
    protected static $files_to_not_copy = array('LICENSE', 'README.txt');
    protected static $manifest_default_install_version_string = "array('^7.9.[\d]+.[\d]+$')";
    protected static $manifest_default_author = 'Enrico Simonetti';

    protected static function getZipName($package_name = '')
    {
        return self::$release_directory . DIRECTORY_SEPARATOR . self::$prefix_release_package . $package_name . '.zip';
    }

    protected static function createDirectory($directory = '')
    {
        if (!empty($directory)) {
            if(!is_dir($directory)) {
                mkdir($directory, 0777, true);
            }
        }
    }
 
    protected static function createAllDirectories()
    {
        self::createDirectory(self::$release_directory);
        self::createDirectory(self::$config_directory);
        self::createDirectory(self::$src_directory);
        self::createDirectory(self::$pkg_directory);
    }

    protected static function buildSimplePath($directory = '', $file = '')
    {
        $path = '';
        if (!empty($directory) && !empty($file)) {
            $path = realpath($directory) . DIRECTORY_SEPARATOR . $file;
        }
        return $path;
    }

    protected static function getDirectoryContentIterator($path)
    {
        return new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(realpath($path), RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
    }

    protected static function getModuleFiles($path)
    {
        $files_iterator = self::getDirectoryContentIterator($path);
        $result = array();
        $path = realpath($path);
        if (!empty($files_iterator) && !empty($path)) {
            foreach ($files_iterator as $name => $file) {
                if ($file->isFile()) {
                    $fileReal = $file->getRealPath();
                    if (!in_array($file->getFilename(), self::$files_to_not_package)) {
                        $fileRelative = '' . str_replace($path . '/', '', $fileReal);
                        $result[$fileRelative] = $fileReal;
                    }
                }
            }
        }
        return $result;
    }

    protected static function wipePkgDirectory()
    {
        $pkg_files = self::getDirectoryContentIterator(self::$pkg_directory);

        if (!empty($pkg_files)) {
            foreach ($pkg_files as $pkg_file) {
                unlink($pkg_file->getPathname());
            }
        }
    }

    protected static function getManifest($version = '')
    {
        $manifest = array();
        if (!empty($version)) {

            // check existence of manifest template
            $manifest_base = array(
                'version' => $version,
                'is_uninstallable' => true,
                'published_date' => date('Y-m-d H:i:s'),
                'type' => 'module',
            );

            if (file_exists(self::buildSimplePath(self::$config_directory, self::$manifest_file))) {
                require(self::buildSimplePath(self::$config_directory, self::$manifest_file));
                $manifest = array_merge_recursive($manifest_base, $manifest);
            } else {
                // create sample empty manifest file
                $manifestContent = "<?php".PHP_EOL."\$manifest['id'] = '';".PHP_EOL.
                    "\$manifest['built_in_version'] = '';".PHP_EOL.
                    "\$manifest['name'] = '';".PHP_EOL.
                    "\$manifest['description'] = '';".PHP_EOL.
                    "\$manifest['author'] = '".self::$manifest_default_author."';".PHP_EOL.
                    "\$manifest['acceptable_sugar_versions']['regex_matches'] = ".self::$manifest_default_install_version_string.";";

                PackageOutput::createFile(self::buildSimplePath(self::$config_directory, self::$manifest_file), $manifestContent);
            }

            if ( empty($manifest['id']) ||
                empty($manifest['built_in_version']) ||
                empty($manifest['name']) ||
                empty($manifest['version']) ||
                empty($manifest['author']) ||
                empty($manifest['acceptable_sugar_versions']['regex_matches']) ) {
                    PackageOutput::message('Please fill in the required details on your ' . self::buildSimplePath(self::$config_directory, self::$manifest_file)  . ' file.');
                    // some problem... return empty manifest
                    return array();
            }
        }
    
        return $manifest;
    }

    protected static function getInstallDefs($manifest, $module_files_list)
    {
        $installdefs_generated = array('copy' => array());
        if(!empty($module_files_list)) {
            foreach($module_files_list as $fileRel => $fileReal) {
                if(!in_array(basename($fileRel), self::$files_to_not_copy)) {
                    $installdefs_generated['copy'][] = array(
                        'from' => '<basepath>/' . $fileRel,
                        'to' => $fileRel,
                    );
                    PackageOutput::message('* Automatically added manifest copy directive for ' . $fileRel);
                } else {
                    PackageOutput::message('* Skipped manifest copy directive for ' . $fileRel);
                }
            }
        }

        $installdefs = array();
        if (empty($installdefs['id']) && !empty($manifest['id'])) {
            $installdefs['id'] = $manifest['id'];
        }
        if (is_dir(self::$config_directory) && file_exists(self::buildSimplePath(self::$config_directory, self::$config_installdefs_file))) {
            require(self::buildSimplePath(self::$config_directory, self::$config_installdefs_file));
        }
        $installdefs = array_merge_recursive($installdefs, $installdefs_generated);

        return $installdefs;
    }

    protected static function copySrcIntoPkg()
    {
        // copy into pkg all src files
        $common_files_list = self::getModuleFiles(self::$src_directory);
        if (!empty($common_files_list)) {
            foreach ($common_files_list as $fileRel => $fileReal) {
                $destination_directory = self::$pkg_directory . DIRECTORY_SEPARATOR . dirname($fileRel) . DIRECTORY_SEPARATOR;
                
                self::createDirectory($destination_directory);
                PackageOutput::copyFile($fileReal, $destination_directory . basename($fileReal));
            }
        }
    }

    protected static function generateZipPackage($manifest, $zipFile)
    {
        PackageOutput::message('Creating ' . $zipFile . '...');
        $zip = new ZipArchive();
        $zip->open($zipFile, ZipArchive::CREATE);

        // add all files to zip
        $module_files_list = self::getModuleFiles(self::$pkg_directory);
        if(!empty($module_files_list)) {
            foreach($module_files_list as $fileRel => $fileReal) {
                $zip->addFile($fileReal, $fileRel);
            }
        }
 
        $installdefs = self::getInstallDefs($manifest, $module_files_list);

        $manifestContent = sprintf(
            "<?php\n\n\$manifest = %s;\n\n\$installdefs = %s;\n",
            //preg_replace('(\s+\d+\s=>)', '', var_export($manifest, true)),
            var_export($manifest, true),
            preg_replace('(\s+\d+\s=>)', '', var_export($installdefs, true))
            //var_export($installdefs, true)
        );

        //$manifestContent = preg_replace('(\s+\d+\s=>)', '', $manifestContent);

        // adding the file as well, for reference purpose only
        PackageOutput::createFile(self::buildSimplePath(self::$pkg_directory, self::$manifest_file), $manifestContent);
        $zip->addFromString(self::$manifest_file, $manifestContent);
        $zip->close();

        PackageOutput::message('Packaged ' . $zipFile);
    }

    public static function build($version = '')
    {
        if (!empty($version)) {
            self::createAllDirectories();
            $manifest = self::getManifest($version);

            if (!empty($manifest)) {
                $zip = self::getZipName($manifest['id'] . '_' . $version);

                if (file_exists($zip)) {
                    PackageOutput::message('Release '.$zip.' already exists!');
                } else {
                    self::wipePkgDirectory();
                    self::copySrcIntoPkg();
                    self::generateTemplatedConfiguredFiles();
                    self::generateZipPackage($manifest, $zip);
                }
            }
        } else {
            PackageOutput::message('Provide version number');
        }
    }

    protected static function generateTemplatedConfiguredFiles()
    {
        if (is_dir(self::$config_directory) && file_exists(self::buildSimplePath(self::$config_directory, self::$config_template_file))) {
            require(self::buildSimplePath(self::$config_directory, self::$config_template_file));

            if (!empty($templates)) {
                foreach($templates as $template_src_directory => $template_values) {
                    if(is_dir(realpath($template_src_directory)) && !empty($template_values['directory_pattern']) && !empty($template_values['modules'])) {
                        $template_dst_directory = $template_values['directory_pattern'];
                        $modules = $template_values['modules'];

                        // generate runtime files based on the templates
                        $template_files_list = self::getModuleFiles($template_src_directory);
                        if (!empty($template_files_list)) {
                            $template_dst_directory = str_replace('/', DIRECTORY_SEPARATOR, $template_dst_directory);
                            
                            foreach ($modules as $module => $object) {
                                PackageOutput::message('* Generating template files for module: ' . $module);
                                // replace modulename from path
                                $current_module_destination = str_replace('{MODULENAME}', $module, $template_dst_directory);
                                foreach ($template_files_list as $fileRel => $fileReal) {
                                    // build destination
                                    $destination_directory = self::$pkg_directory . DIRECTORY_SEPARATOR . $current_module_destination . DIRECTORY_SEPARATOR . dirname($fileRel) . DIRECTORY_SEPARATOR;
                                    PackageOutput::message('* Generating '.$destination_directory . basename($fileRel));
                                    
                                    self::createDirectory($destination_directory);
                                    PackageOutput::copyFile($fileReal, $destination_directory . basename($fileRel));

                                    // modify content
                                    $content = file_get_contents($destination_directory . basename($fileRel));
                                    $content = str_replace('{MODULENAME}', $module, $content);
                                    $content = str_replace('{OBJECTNAME}', $object, $content);
                                    PackageOutput::createFile($destination_directory . basename($fileRel), $content);
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}
