<?php
/**
 * Plugin Name: Term Pages - DEV
 * Description:       Dev inc file
 * Version:           X.X.X
 * Requires at least: X.X
 * Tested up to:      X.X.X
 * Author:            Palasthotel <webmaster@palasthotel.de>
 * Author URI:        https://palasthotel.de
 */

defined( 'ABSPATH' ) || exit;

// "Plugin Name:" is followed by a single space on purpose, unlike the aligned fields
// above: the shared PR check looks for the literal "Plugin Name: Term Pages - DEV" in
// the payload, and would not recognise this file with the alignment.

include dirname( __FILE__ ) . "/public/term-pages.php";
