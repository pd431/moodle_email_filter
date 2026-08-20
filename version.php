<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Version details for the "Auto-link email addresses" text filter.
 *
 * @package    filter_emailautolink
 * @copyright  2026 Auto-link email addresses filter contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'filter_emailautolink';
$plugin->version    = 2026082000;
$plugin->requires   = 2022112800; // Moodle 4.1 (introduces core_filters\text_filter).
$plugin->maturity   = MATURITY_STABLE;
$plugin->release    = '1.0.0';
