<?php
/**
 * Plugin Name:         Ultimate Member - Import Profile Photos
 * Description:         Extension to Ultimate Member for importing Profile photos.
 * Version:             1.1.0
 * Requires PHP:        7.4
 * Author:              Miss Veronica
 * License:             GPL v3 or later
 * License URI:         https://www.gnu.org/licenses/gpl-2.0.html
 * Author URI:          https://github.com/MissVeronica
 * Plugin URI:          https://github.com/MissVeronica/um-import-profile-photos
 * Update URI:          https://github.com/MissVeronica/um-import-profile-photos
 * Text Domain:         ultimate-member
 * Domain Path:         /languages
 * UM version:          2.9.2
 */

if ( ! defined( 'ABSPATH' ) ) exit; 
if ( ! class_exists( 'UM' ) ) return;


class UM_Import_Profile_Photos {

    public $original_width  = '';
    public $original_height = '';
    public $supported_exts  = array( 'gif', 'jpg', 'png', 'bmp' );

    function __construct() {

        define( 'Plugin_Basename_IPP', plugin_basename( __FILE__ ));

        add_filter( 'um_pre_args_setup',         array( $this, 'add_new_imported_profile_photos' ), 10, 1 );
        add_filter( 'um_predefined_fields_hook', array( $this, 'predefined_fields_hook_import_profile_photos' ), 10, 1 );

        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {

            add_filter( 'um_settings_structure', array( $this, 'create_setting_structures' ), 10, 1 );
            add_filter( 'plugin_action_links_' . Plugin_Basename_IPP, array( $this, 'plugin_settings_link' ), 10, 1 );
        }
    }

    public function plugin_settings_link( $links ) {

        $url = get_admin_url() . "admin.php?page=um_options&tab=appearance";
        $links[] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings' ) . '</a>';

        return $links;
    }

    public function add_new_imported_profile_photos( $args ) {

        global $current_user;

        if ( $args['mode'] != 'profile' ) {
            return $args;
        }

        $user_id = um_user( 'ID' );
        $profile_photo = get_user_meta( $user_id, 'profile_photo', true );

        if ( empty( $profile_photo ) || UM()->options()->get( 'profile_import_photo_reuse_key' ) == 1 ) {

            $user_path = $this->get_um_filesystem( 'base_dir' ) . $user_id . DIRECTORY_SEPARATOR;

            if ( ! empty( $profile_photo ) && $current_user->ID != $user_id ) {

                if ( filemtime( $user_path . $profile_photo ) + HOUR_IN_SECONDS > time()) {
                    return $args;
                }
            }

            if ( $current_user->ID == $user_id && UM()->options()->get( 'profile_import_photo_reuse_key' ) == 1 ) {
                $args['disable_photo_upload'] = 1;
            }

            $meta_key = sanitize_text_field( UM()->options()->get( 'profile_import_photo_key' ));
            if ( ! empty( $meta_key ) && $meta_key != 'profile_photo' ) {

                $source_path = um_user( $meta_key );

                if ( ! empty( $source_path )) {

                    $result = false;

                    if ( str_contains( $source_path, '/wp-content/uploads/' )) {

                        if ( substr( $source_path, 0, strlen( ABSPATH )) !== ABSPATH ) {
                            $source_path = ABSPATH . $source_path;
                        }

                        $ext = pathinfo( $source_path, PATHINFO_EXTENSION );
                        if ( in_array( $ext, $this->supported_exts )) {

                            $this->remove_current_profile_photos( $user_path );
                            $result = copy( $source_path, $user_path . 'profile_photo.' . $ext );
                        }
                    }

                    if ( substr( $source_path, 0, 8 ) == 'https://' ) {

                        if ( $this->validate_remote_url( $source_path )) {

                            $content = file_get_contents( $source_path );
                            $finfo   = new finfo( FILEINFO_MIME_TYPE );
                            $type    = $finfo->buffer( $content );

                            switch( $type ) {

                                case "image/gif":   $ext = 'gif'; break;
                                case "image/jpeg":
                                case "image/jpg":   $ext = 'jpg'; break;
                                case "image/png":   $ext = 'png'; break;
                                case "image/bmp":   $ext = 'bmp'; break;
                                default:            $ext = 'xxx'; break;
                            }

                            if ( in_array( $ext, $this->supported_exts )) {

                                $this->remove_current_profile_photos( $user_path );
                                $result = file_put_contents( $user_path . 'profile_photo.' . $ext, $content );
                            }
                        }
                    }

                    if ( $result ) {

                        update_user_meta( $user_id, 'profile_photo', 'profile_photo.' . $ext );

                        $this->create_resized_profile_photos( $user_path, $ext );

                        UM()->user()->remove_cache( $user_id );
                        um_fetch_user( $user_id );
                    }
                }
            }
        }

        return $args;
    }

    public function create_resized_profile_photos( $user_path, $ext ) {

        $profile_sizes = array_map( 'sanitize_key', UM()->options()->get( 'photo_thumb_sizes' ));
        if ( is_array( $profile_sizes ) && ! empty( $profile_sizes )) {

            $quality = sanitize_key( UM()->options()->get( 'image_compression' ));
            $quality = ( empty( $quality )) ? '100' : $quality;
            $original_photo = $user_path . 'profile_photo.' . $ext;

            foreach( $profile_sizes as $profile_size ) {

                $new_file = $user_path . 'profile_photo-' . "{$profile_size}x{$profile_size}" . '.' . $ext;

                $result = $this->image_resize( $original_photo, $new_file, $profile_size, $profile_size, $quality, $ext, true );
            }

            $profile_size = min( $this->original_width, $this->original_height );
            $result = $this->image_resize( $original_photo, $original_photo, $profile_size, $profile_size, $quality, $ext, true );
        }
    }

    public function image_resize( $src, $dst, $width, $height, $quality, $ext, $crop = 0 ) {

        list( $w, $h ) = getimagesize( $src );

        $this->original_width  = $w;
        $this->original_height = $h;

        if ( $w < $width || $h < $height ) {
            return "Picture is too small";
        }

        switch( $ext ) {

            case 'bmp': $img = imagecreatefromwbmp( $src ); break;
            case 'gif': $img = imagecreatefromgif(  $src ); break;
            case 'jpg': $img = imagecreatefromjpeg( $src ); break;
            case 'png': $img = imagecreatefrompng(  $src ); break;
            default : return "Unsupported picture type";
        }

        // resize
        if ( $crop ) {

            $ratio = max( $width/$w, $height/$h );
            $h = intval( $height / $ratio );
            $x = intval( ( $w - $width / $ratio ) / 2 );
            $w = intval( $width / $ratio );

        } else {

            $ratio  = min( $width/$w, $height/$h );
            $width  = intval( $w * $ratio );
            $height = intval( $h * $ratio );
            $x = 0;
        }

        $new = imagecreatetruecolor( $width, $height );

        // preserve transparency
        if ( $ext == "gif" || $ext == "png" ) {

            imagecolortransparent( $new, imagecolorallocatealpha( $new, 0, 0, 0, 127 ));
            imagealphablending( $new, false );
            imagesavealpha( $new, true );
        }

        imagecopyresampled( $new, $img, 0, 0, $x, 0, $width, $height, $w, $h );

        switch( $ext ) {

            case 'bmp': imagewbmp( $new, $dst, $quality ); break;
            case 'gif': imagegif(  $new, $dst, $quality ); break;
            case 'jpg': imagejpeg( $new, $dst, $quality ); break;
            case 'png': imagepng(  $new, $dst, $quality ); break;
        }

        return true;
    }

    public function validate_remote_url( $source_path ) {

        $valid = false;
        $valid_urls = array_map( 'sanitize_text_field', array_map( 'trim', explode( "\n", UM()->options()->get( 'profile_import_photo_url' ))));

        if ( is_array( $valid_urls ) && ! empty( $valid_urls )) {

            foreach( $valid_urls as $valid_url ) {

                if ( ! empty( $valid_url ) && substr( $source_path, 0, strlen( $valid_url )) == $valid_url ) {
                    $valid = true;
                    break;
                }
            }
        }

        return $valid;
    }

    public function remove_current_profile_photos( $user_path ) {

        $matches = glob( $user_path . 'profile_photo*', GLOB_MARK );
        if ( is_array(  $matches ) && ! empty( $matches )) {

            foreach( $matches as $match ) {

                if ( is_file( $match ) && ! is_dir( $match ) ) {
                    unlink( $match );
                }
            }
        }
    }

    public function get_um_filesystem( $function ) {

        if ( version_compare( ultimatemember_version, '2.9.3' ) == -1 ) {

            switch( $function ) {
                case 'base_dir': $value = UM()->uploader()->get_upload_base_dir(); break;
                case 'base_url': $value = UM()->uploader()->get_upload_base_url(); break;
            }

        } else {

            switch( $function ) {
                case 'base_dir': $value = UM()->common()->filesystem()->get_basedir(); break;
                case 'base_url': $value = UM()->common()->filesystem()->get_baseurl(); break;
            }
        }

        return $value;
    }

    public function get_possible_plugin_update( $plugin ) {

        $plugin_data = get_plugin_data( __FILE__ );

        $documention = sprintf( ' <a href="%s" target="_blank" title="%s">%s</a>',
                                        esc_url( $plugin_data['PluginURI'] ),
                                        esc_html__( 'GitHub plugin documentation and download', 'ultimate-member' ),
                                        esc_html__( 'Documentation', 'ultimate-member' ));

        $description = sprintf( esc_html__( 'Plugin "Import Profile Photos" version %s - tested with UM 2.9.2 - %s', 'ultimate-member' ),
                                                                            $plugin_data['Version'], $documention );

        return $description;
    }

    public function create_setting_structures( $settings_structure ) {

        $prefix = '&nbsp; * &nbsp;';

        if ( UM()->options()->get( 'profile_import_photo_reuse_key' ) == 1 && UM()->options()->get( 'disable_profile_photo_upload' ) != 1 ) {
            UM()->options()->update( 'disable_profile_photo_upload', 1 );
        }

        $settings_structure['appearance']['sections']['']['form_sections']['import_profile_photos']['title'] = esc_html__( 'Import Profile Photos', 'ultimate-member' );
        $settings_structure['appearance']['sections']['']['form_sections']['import_profile_photos']['description'] = $this->get_possible_plugin_update( 'import_profile_photos' );

        $settings_structure['appearance']['sections']['']['form_sections']['import_profile_photos']['fields'][] = array(
                                'id'             => 'profile_import_photo_key',
                                'type'           => 'text',
                                'label'          => $prefix . esc_html__( 'Meta_key with User Profile photo source address', 'ultimate-member' ),
                                'description'    => esc_html__( "Profile photo source address can contain either a wp-content path or an URL with 'https://'. Input of 'profile_photo' can't be used here.", 'ultimate-member' ),
                                'size'           => 'medium',
                            );

        $settings_structure['appearance']['sections']['']['form_sections']['import_profile_photos']['fields'][] = array(
                                'id'             => 'profile_import_photo_url',
                                'type'           => 'textarea',
                                'label'          => $prefix . esc_html__( 'Valid URLs for User Profile photo source address', 'ultimate-member' ),
                                'description'    => esc_html__( "Enter valid URLs for Profile photo source address one per line.", 'ultimate-member' ),
                            );

        $settings_structure['appearance']['sections']['']['form_sections']['import_profile_photos']['fields'][] = array(
                                'id'             => 'profile_import_photo_reuse_key',
                                'type'           => 'checkbox',
                                'label'          => $prefix . esc_html__( 'Reuse Meta_key with Profile photo source address', 'ultimate-member' ),
                                'checkbox_label' => esc_html__( 'Tick to reuse (for future photo updates) the Profile photo source address meta_key and update UM Profile photo via the source address each time when Profile is viewed.', 'ultimate-member' ),
                            );

        return $settings_structure;
    }

    public function predefined_fields_hook_import_profile_photos( $predefined_fields ) {

        $title = esc_html__( 'Import Profile Photo', 'ultimate-member' );
        $meta_key = sanitize_text_field( UM()->options()->get( 'profile_import_photo_key' ));

        if ( ! empty( $meta_key )) {

            $predefined_fields[$meta_key] = array(
                                                    'title'    => $title,
                                                    'metakey'  => $meta_key,
                                                    'type'     => 'text',
                                                    'label'    => $title,
                                                    'required' => 0,
                                                    'public'   => 1,
                                                    'editable' => true,
                                                );
        }

        return $predefined_fields;
    }

}

new UM_Import_Profile_Photos();

