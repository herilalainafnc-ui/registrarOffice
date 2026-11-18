<?php
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Feuilles de style du theme UAZ Campus.
 *
 * @package    theme_uaz
 * @copyright  2026 Universite Adventiste Zurcher
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Le preregle de Boost, puis ce que ce theme y ajoute : la police et les pages
 * de connexion (uaz.scss), la coque du registrar sur toutes les pages (coque.scss).
 *
 * @param theme_config $theme
 * @return string
 */
function theme_uaz_get_main_scss_content($theme) {
    return file_get_contents(dirname(__DIR__) . '/boost/scss/preset/default.scss') . "\n"
        . file_get_contents(__DIR__ . '/scss/uaz.scss') . "\n"
        . file_get_contents(__DIR__ . '/scss/coque.scss');
}

/**
 * Les variables, avant le preregle : couleurs et police de l'universite.
 *
 * @param theme_config $theme
 * @return string
 */
function theme_uaz_get_pre_scss($theme) {
    return file_get_contents(__DIR__ . '/scss/pre.scss');
}
