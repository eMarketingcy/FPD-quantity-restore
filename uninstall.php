<?php
/**
 * Uninstall handler for FPD Quantity Restore
 */
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete global option
delete_option('fpd_qr_global_enable');

// Note: We intentionally do not delete per-product post meta to preserve admin intent/preferences.
// If you want to wipe: delete_post_meta_by_key('_fpd_allow_qty');
