<?php
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace theme_uaz\privacy;

/**
 * Le theme ne garde aucune donnee personnelle.
 *
 * @package    theme_uaz
 * @copyright  2026 Universite Adventiste Zurcher
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements \core_privacy\local\metadata\null_provider {
    /**
     * La raison, pour le registre de la vie privee.
     *
     * @return string
     */
    public static function get_reason(): string {
        return 'privacy:metadata';
    }
}
