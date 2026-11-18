<?php
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Theme UAZ Campus, enfant de Boost.
 *
 * Il garde tout Boost (gabarits, blocs, tiroirs) et l'habille comme le registrar :
 * - les couleurs, la police et les formes du registrar sur toutes les pages, en
 *   mode clair et en mode sombre (scss/pre.scss : les palettes de Moodle sont
 *   remplacees par celles du registrar) ;
 * - la coque du registrar (scss/coque.scss) : barre du haut bleu Zurcher au
 *   filet or, volet des cours bleu nuit, l'or pour ce qui est actif ;
 * - la page de connexion, dans la composition de celle du registrar
 *   (templates/core/login_layout.mustache, templates/core/loginform.mustache).
 *
 * Les modes clair et sombre sont ceux de Boost (theme_boost | enablecolourmodes,
 * defaultcolourmode) : sombre par defaut, comme le registrar ; chacun bascule
 * par le soleil et la lune de la barre du haut.
 *
 * @package    theme_uaz
 * @copyright  2026 Universite Adventiste Zurcher
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/lib.php');

$THEME->name = 'uaz';
$THEME->parents = ['boost'];
$THEME->sheets = [];
$THEME->editor_sheets = [];
$THEME->usefallback = true;
$THEME->scss = function($theme) {
    return theme_uaz_get_main_scss_content($theme);
};
$THEME->prescsscallback = 'theme_uaz_get_pre_scss';
$THEME->enable_dock = false;
$THEME->yuicssmodules = [];
$THEME->rendererfactory = 'theme_overridden_renderer_factory';
$THEME->requiredblocks = '';
$THEME->addblockposition = BLOCK_ADDBLOCK_POSITION_FLATNAV;
$THEME->iconsystem = \core\output\icon_system::FONTAWESOME;
$THEME->haseditswitch = true;
$THEME->usescourseindex = true;
$THEME->activityheaderconfig = [
    'notitle' => true,
];
