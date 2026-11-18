<?php
// Theme UAZ Campus : Moodle aux couleurs de l'Universite Adventiste Zurcher et du registrar.
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Version du theme UAZ Campus.
 *
 * @package    theme_uaz
 * @copyright  2026 Universite Adventiste Zurcher
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'theme_uaz';
$plugin->version   = 2026101901;
$plugin->release   = '1.1';
$plugin->maturity  = MATURITY_STABLE;
$plugin->requires  = 2026100200;               // Moodle 5.3, comme Boost
$plugin->dependencies = ['theme_boost' => 2026100500];
