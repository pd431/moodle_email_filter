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
 * English language strings for filter_emailautolink.
 *
 * @package    filter_emailautolink
 * @copyright  2026 Pierre Camaly de Brosses - University of Exeter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['filtername'] = 'Auto-link email addresses';
$string['filterorder_desc'] = 'Put this filter above "Email protection" in the list on the Manage filters page. Email protection then hides the address inside the link this filter makes. If Email protection runs first, it hides addresses from this filter and nothing is linked.';
$string['overrideexisting'] = 'Override existing email links';
$string['overrideexisting_desc'] = 'When enabled, an existing mailto: link whose whole visible text is one email address is changed to send to that address. Anything after the address in the link, such as a subject or cc, is kept. Web links and mailto: links with any other text are not changed.';
$string['pluginname'] = 'Auto-link email addresses';
$string['privacy:metadata'] = 'The Auto-link email addresses filter does not store any personal data.';
