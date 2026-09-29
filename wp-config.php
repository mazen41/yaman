<?php
define( 'WP_CACHE', true );




/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the
 * installation. You don't have to use the web site, you can
 * copy this file to "wp-config.php" and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * MySQL settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://codex.wordpress.org/Editing_wp-config.php
 *
 * @package WordPress
 */
// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', "u945462263_kDYrk" );
/** MySQL database username */
define( 'DB_USER', "u945462263_OWYXm" );
/** MySQL database password */
define( 'DB_PASSWORD', "SO6dzj7u3u" );
/** MySQL hostname */
define( 'DB_HOST', "127.0.0.1" );
/** Database Charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );
/** The Database Collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );
/**
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',          '^vTgbo9a<NS,OS|crSrs{hM/?Yv1Xna%-QKH/Yxc6)p{VU@$n2lhB>pWflkCi4M9' );
define( 'SECURE_AUTH_KEY',   'cJAp]{|;gt[,(f~,kJD2|@d%[JMZCjciOgU`X_sW^H+8m2d~8VZVmDJ-nWnP{7M(' );
define( 'LOGGED_IN_KEY',     'XjpJL6$0?U9!*}b2qKpu6]1AXUBE@`e|gH(JHc*-Hi|deRy>G:&rvhx%!Gw^}S k' );
define( 'NONCE_KEY',         'g2hUndi;SvT[GSg#6%JOTdP%M;[w9_UJU{C1!|-[habfde;sma);XGs<M|XFW8h=' );
define( 'AUTH_SALT',         'S0|1fC_ #yiTv0GN%}~vD$$::A2o<dT/`<}jk6A`%n7 (c}=]fR1TJxPR=FGz{w)' );
define( 'SECURE_AUTH_SALT',  'Dps$Qb.O^2flZsF3t!hU`?w}l&/}ix+`BbAz`^qa6Cv}ius?2JLslK?&1FPkm@Q?' );
define( 'LOGGED_IN_SALT',    '(2jM]~7-wY466FUaumXUKJVpP~L _.B/b:C+_B0bF)Ml0`$b=O8)>CVzT^jjJ<v*' );
define( 'NONCE_SALT',        'h3<E`?/HL`pQ#5qd`,.#TA8/0g2XH!7au+k?$fO9NT1w=$K<C<}^Kb3)(Rm[~+W}' );
define( 'WP_CACHE_KEY_SALT', '%|,m0|;SH(vq8KZ!3uH}8Sv2^:aPE6B_Ht!e2.llz(Jm[uI=9jC]j)d.+YS7jFUC' );
define( 'WP_DEBUG', true );
// added by Zuhair start

define('WP_DEBUG_DISPLAY', false);
define('WP_DEBUG_LOG', true);

// added by Zuhair end
/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';

define( 'WP_HOME', 'https://loop-pr.com' ); 
define( 'WP_SITEURL', 'https://loop-pr.com' );

define( 'FS_METHOD', 'direct' );
define( 'DUPLICATOR_AUTH_KEY', 'a8967a0d460964a11a2abfdd416ba9f6' );
/* That's all, stop editing! Happy publishing. */
/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';


