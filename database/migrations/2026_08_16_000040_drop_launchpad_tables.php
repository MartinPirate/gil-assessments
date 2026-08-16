<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Removes the launchpad's tables along with the plugin.
 *
 * The launchpad was a grid of tiles at the panel root, one per screen already
 * listed in the sidebar beside it. It made signing in land on a menu rather
 * than on the dashboard, and the dashboard had to be pushed off '/' to make
 * room for it. Both are undone.
 *
 * The plugin owned these migrations, so uninstalling it takes the definitions
 * away without touching a database that already has the tables — hence
 * dropping them here rather than relying on the package.
 *
 * Children first: launchpad_section_card and launchpad_user_cards hold foreign
 * keys into the rest, and SQL Server will not drop a table something still
 * points at.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'launchpad_section_card',
            'launchpad_user_cards',
            'launchpad_role_visibility',
            'launchpad_cards',
            'launchpad_sections',
            'launchpad_pages',
            'launchpad_spaces',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }

    public function down(): void
    {
        // Nothing to restore: the tables belonged to a package that is no
        // longer installed, so recreating them here would leave a shape with
        // nothing to read or write it.
    }
};
