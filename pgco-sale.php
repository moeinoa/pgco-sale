<?php
/**
 * Plugin Name: PGCO Sale (فروش ویژه)
 * Description: سکشن اسلایدر فروش ویژه و نوار تبلیغاتی بالای هدر. ردیف‌ها را از جداول افزونهٔ PGCO Tables انتخاب می‌کنید. شورتکدها: [pgco_sale] و [pgco_sale_bar]
 * Version: 1.1.1
 * Author: Moein Akbari
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Text Domain: pgco-sale
 */

defined( 'ABSPATH' ) || exit;

define( 'PGS_VERSION', '1.1.1' );
define( 'PGS_DIR', plugin_dir_path( __FILE__ ) );
define( 'PGS_URL', plugin_dir_url( __FILE__ ) );
define( 'PGS_OPTION', 'pgco_sale' );
define( 'PGS_TABLE_CPT', 'table' ); // Post type of the PGCO Tables plugin

spl_autoload_register( static function ( string $class ): void {
    if ( ! str_starts_with( $class, 'PGCO_Sale\\' ) ) {
        return;
    }
    $name = strtolower( str_replace( [ 'PGCO_Sale\\', '_' ], [ '', '-' ], $class ) );
    $file = PGS_DIR . 'includes/class-' . $name . '.php';
    if ( is_readable( $file ) ) {
        require_once $file;
    }
} );

add_action( 'plugins_loaded', static function (): void {
    PGCO_Sale\Render::init();
    if ( is_admin() ) {
        PGCO_Sale\Admin::init();
    }
} );
