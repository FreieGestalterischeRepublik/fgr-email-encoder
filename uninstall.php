<?php
// Wird nur ausgeführt, wenn das Plugin über WordPress gelöscht wird
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;

delete_option( 'fgr_email_encoder' );
