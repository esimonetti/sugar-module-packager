<?php

// Enrico Simonetti
// enricosimonetti.com
//
// 2018-01-29 on Sugar 7.9.3.0
//
// Tool that helps package Sugar installable modules

namespace SugarModulePackager;

use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use ZipArchive;

class PackageUtils
{
    const SW_VERSION = '0.2.2';
    const SW_NAME = 'SugarModulePackager';

    protected static $release_directory = 'releases';
    protected static $prefix_release_package = 'module_';
    protected static $config_directory = 'configuration';
    protected static $config_template_file = 'templates.php';
    protected static $config_installdefs_file = 'installdefs.php';
    protected static $src_directory = 'src';
    protected static $pkg_directory = 'pkg';
    protected static $manifest_file = 'manifest.php';

    protected static $files_to_remove_from_zip = array(
        '.DS_Store',
        '.gitkeep'
    );
    protected static $files_to_remove_from_manifest_copy = array(
        'LICENSE',
        'LICENSE.txt',
        'README.txt'
    );
    protected static $installdefs_keys_to_remove_from_manifest_copy = array(
        'pre_execute',
        'post_execute',
        'pre_uninstall',
        'post_uninstall'
    );
    protected static $manifest_default_install_version_string = "array('^8.[\d]+.[\d]+$')";
    protected static $manifest_default_author = 'Enrico Simonetti';

    private static function getSoftwareVersionNumber()
    {
        return self::SW_VERSION;
    }
    
    private static function getSoftwareName()
    {
        return self::SW_NAME;
    }

    public static function getSoftwareInfo()
    {
        return self::getSoftwareName() . ' v' . self::getSoftwareVersionNumber();
    }

    protected static function getZipName($package_name = '')
    {
        return self::$release_directory . DIRECTORY_SEPARATOR . self::$prefix_release_package . $package_name . '.zip';
    }

    protected static function createAllDirectories()
    {
        PackageOutput::createDirectory(self::$release_directory);
        PackageOutput::createDirectory(self::$config_directory);
        PackageOutput::createDirectory(self::$src_directory);
        PackageOutput::createDirectory(self::$pkg_directory);
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
                    $file_realpath = $file->getRealPath();
                    if (!in_array($file->getFilename(), self::$files_to_remove_from_zip)) {
                        $file_relative = '' . str_replace($path . '/', '', $file_realpath);
                        $result[$file_relative] = $file_realpath;
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
                $manifest = array_replace_recursive($manifest_base, $manifest);
            } else {
                // create sample empty manifest file
                $manifestContent = "<?php".PHP_EOL."\$manifest['id'] = '';".PHP_EOL.
                    "\$manifest['built_in_version'] = '';".PHP_EOL.
                    "\$manifest['name'] = '';".PHP_EOL.
                    "\$manifest['description'] = '';".PHP_EOL.
                    "\$manifest['author'] = '".self::$manifest_default_author."';".PHP_EOL.
                    "\$manifest['acceptable_sugar_versions']['regex_matches'] = ".self::$manifest_default_install_version_string.";";

                PackageOutput::writeFile(self::buildSimplePath(self::$config_directory, self::$manifest_file), $manifestContent);
            }

            if ( empty($manifest['id']) ||
                empty($manifest['built_in_version']) ||
                empty($manifest['name']) ||
                empty($manifest['version']) ||
                empty($manifest['author']) ||
                empty($manifest['acceptable_sugar_versions']['regex_matches']) ) {
                    PackageOutput::message('Please fill in the required details on your ' .
                        self::buildSimplePath(self::$config_directory, self::$manifest_file)  . ' file.');
                    // some problem... return empty manifest
                    return array();
            }
        }
    
        return $manifest;
    }

    protected static function getInstallDefs($manifest, $module_files_list)
    {
        $installdefs = array();
        $installdefs_original = array();
        $installdefs_generated = array('copy' => array());

        if (!empty($manifest['id'])) {
            $installdefs_original['id'] = $manifest['id'];
        }

        if (is_dir(self::$config_directory) && 
                file_exists(self::buildSimplePath(self::$config_directory, self::$config_installdefs_file))) {
            require(self::buildSimplePath(self::$config_directory, self::$config_installdefs_file));
        }

        if (!empty($module_files_list)) {
            foreach ($module_files_list as $file_relative => $file_realpath) {
                if (self::shouldAddToManifestCopy($file_relative, $installdefs)) {
                    $installdefs_generated['copy'][] = array(
                        'from' => '<basepath>/' . $file_relative,
                        'to' => $file_relative,
                    );
                    PackageOutput::message('* Automatically added manifest copy directive for ' . $file_relative);
                } else {
                    PackageOutput::message('* Skipped manifest copy directive for ' . $file_relative);
                }
            }
        }

        $installdefs = array_replace_recursive($installdefs_original, $installdefs, $installdefs_generated);

        return $installdefs;
    }

    protected static function shouldAddToManifestCopy($file_relative, $custom_installdefs)
    {
        if (!in_array(basename($file_relative), self::$files_to_remove_from_manifest_copy)) {
            // check and dont copy all *_execute and *_uninstall installdefs keyword files
            foreach (self::$installdefs_keys_to_remove_from_manifest_copy as $to_remove) {
                if (!empty($custom_installdefs[$to_remove])) {
                    foreach ($custom_installdefs[$to_remove] as $manifest_file_copy) {
                        // found matching relative file as one of the *_execute or *_uninstall scripts
                        if (strcmp(str_replace('<basepath>/', '', $manifest_file_copy), $file_relative) == 0) {
                            return false;
                        }
                    }
                }
            }
            return true;
        }
        return false;
    }
    
    protected static function copySrcIntoPkg()
    {
        // copy into pkg all src files
        $common_files_list = self::getModuleFiles(self::$src_directory);
        if (!empty($common_files_list)) {
            foreach ($common_files_list as $file_relative => $file_realpath) {
                $destination_directory = self::$pkg_directory . DIRECTORY_SEPARATOR . dirname($file_relative) . DIRECTORY_SEPARATOR;
                
                PackageOutput::createDirectory($destination_directory);
                PackageOutput::copyFile($file_realpath, $destination_directory . basename($file_realpath));
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
        if (!empty($module_files_list)) {
            foreach ($module_files_list as $file_relative => $file_realpath) {
                $zip->addFile($file_realpath, $file_relative);
            }
        }
 
        $installdefs = self::getInstallDefs($manifest, $module_files_list);
        
        if (!empty($installdefs['copy'])) {
            $installdefs_copy = $installdefs['copy'];
            unset($installdefs['copy']);
        } else {
            $installdefs_copy = array();
        }

        $manifestContent = sprintf(
            "<?php\n\n\$manifest = %s;\n\n\$installdefs = %s;\n\n\$installdefs['copy'] = %s;\n",
            var_export($manifest, true),
            var_export($installdefs, true),
            preg_replace('(\s+\d+\s=>)', '', var_export($installdefs_copy, true))
        );

        // adding the file as well, for reference purpose only
        PackageOutput::writeFile(self::buildSimplePath(self::$pkg_directory, self::$manifest_file), $manifestContent);
        $zip->addFromString(self::$manifest_file, $manifestContent);
        $zip->close();

        PackageOutput::message(self::getSoftwareInfo() . ' successfully packaged ' . $zipFile);
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
                foreach ($templates as $template_src_directory => $template_values) {
                    if (is_dir(realpath($template_src_directory)) && 
                            !empty($template_values['directory_pattern']) && !empty($template_values['modules'])) {
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
                                foreach ($template_files_list as $file_relative => $file_realpath) {
                                    // build destination
                                    $destination_directory = self::$pkg_directory . DIRECTORY_SEPARATOR . $current_module_destination . 
                                        DIRECTORY_SEPARATOR . dirname($file_relative) . DIRECTORY_SEPARATOR;
                                    PackageOutput::message('* Generating '.$destination_directory . basename($file_relative));
                                    
                                    PackageOutput::createDirectory($destination_directory);
                                    PackageOutput::copyFile($file_realpath, $destination_directory . basename($file_relative));

                                    // modify content
                                    $content = PackageOutput::readFile($destination_directory . basename($file_relative));
                                    $content = str_replace('{MODULENAME}', $module, $content);
                                    $content = str_replace('{OBJECTNAME}', $object, $content);
                                    PackageOutput::writeFile($destination_directory . basename($file_relative), $content);
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}
