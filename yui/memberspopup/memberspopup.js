// This file is part of mod_grouptool for Moodle - http://moodle.org/
//
// It is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// It is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * memberspopup.js
 *
 * @package       mod_grouptool
 * @author        Andreas Hruska (andreas.hruska@tuwien.ac.at)
 * @author        Katarzyna Potocka (katarzyna.potocka@tuwien.ac.at)
 * @author        Philipp Hager
 * @copyright     2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license       http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

YUI.add('moodle-mod_grouptool-memberspopup', function (Y, NAME) {

function MEMBERSPOPUP() {
    MEMBERSPOPUP.superclass.constructor.apply(this, arguments);
}

var SELECTORS = {
        CLICKABLELINKS: 'span.memberstooltip > a',
        FOOTER: 'div.moodle-dialogue-ft'
    },

    CSS = {
        ICON: 'icon',
        ICONPRE: 'icon-pre'
    },
    ATTRS = {};

// Set the modules base properties.
MEMBERSPOPUP.NAME = 'moodle-mod_grouptool-memberspopup';
MEMBERSPOPUP.ATTRS = ATTRS;

Y.extend(MEMBERSPOPUP, Y.Base, {
    panel: null,

    initializer: function() {
        Y.one('body').delegate('click', this.display_panel, SELECTORS.CLICKABLELINKS, this);
    },

    display_panel: function(e) {
        if (!this.panel) {
            this.panel = new M.core.tooltip({
                bodyhandler: this.set_body_content,
                footerhandler: this.set_footer,
                initialheadertext: M.util.get_string('loading', 'mod_grouptool'),
                initialfootertext: ''
            });
        }

        // Call the tooltip setup.
        this.panel.display_panel(e);
    }
});

M.mod_grouptool = M.mod_grouptool || {};
M.mod_grouptool.memberspopup = M.mod_grouptool.memberspopup || null;
M.mod_grouptool.init_memberspopup = M.mod_grouptool.init_memberspopup || function(config) {
    // Only set up a single instance of the memberspopup.
    if (!M.mod_grouptool.memberspopup) {
        M.mod_grouptool.memberspopup = new MEMBERSPOPUP(config);
    }
    return M.mod_grouptool.membespopup;
};

}, '@VERSION@', {"requires": ["moodle-core-tooltip"]});
