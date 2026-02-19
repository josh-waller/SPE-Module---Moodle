<?php
// This file is essential for the installation and upgrade of the plugin
// Documentation: https://moodledev.io/docs/4.1/apis/commonfiles/version.php 

/**
 * - version number must be increased to upgrade the plugin
 * - version number must be the same as upgragde.php
 */


defined('MOODLE_INTERNAL') || die();

$plugin->component = 'mod_speval';
$plugin->version   = 2025102700;   // YYYYMMDDXX
$plugin->requires  = 2021051700;   // Moodle 4.0 minimum
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = 'v1.0';