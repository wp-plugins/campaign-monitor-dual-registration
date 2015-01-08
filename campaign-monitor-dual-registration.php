<?php
/*
Plugin Name: Campaign Monitor Dual Registration
Version: 1.0.7
Author: Carlo Roosen, Elena Mukhina
Author URI: http://www.carloroosen.com/
*/

define( 'CMDR_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'CMDR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Global variables
global $cmdr_fields_to_hide;

register_deactivation_hook( __FILE__, 'cmdr_default_settings' );
register_deactivation_hook( __FILE__, 'cmdr_webhook_remove' );

add_action( 'admin_init', 'cmdr_settings' );
add_action( 'admin_menu', 'cmdr_settings_menu' );
add_action( 'init', 'cmdr_init' );
add_action( 'profile_update', 'cmdr_user_update', 10, 2 );
add_action( 'update_option_cmdr_settings', 'cmdr_save_and_sync' );
add_action( 'user_register', 'cmdr_user_insert' );
add_action( 'wp_ajax_cmdr-cm-sync', 'cmdr_cm_sync' );
add_action( 'wp_ajax_nopriv_cmdr-cm-sync', 'cmdr_cm_sync' );

add_filter( 'update_user_metadata', 'cmdr_user_meta_update', 1000, 5 );

require_once CMDR_PLUGIN_PATH . 'classes/CMDR_Dual_Synchronizer.php';

function cmdr_default_settings() {
	if ( ! get_option( 'cmdr_settings' ) ) {
		remove_action( 'update_option_cmdr_settings', 'cmdr_save_and_sync' );
		
		$cmdr_settings = array(
			'sync_timestamp' => 0,
			'user_fields' => array(),
			'api_key' => '',
			'list_id' => '',
			'cm_sync' => 0
		);
		update_option( 'cmdr_settings', $cmdr_settings );
	}
}

function cmdr_webhook_remove() {
	$cmdr_settings = ( array ) get_option( 'cmdr_settings' );
	$cmdr_settings[ 'cm_sync' ] = 0;
	update_option( 'cmdr_settings', $cmdr_settings );
}

function cmdr_settings() {
	register_setting( 'cmdr_settings_group', 'cmdr_settings', 'cmdr_settings_sanitize' );

	add_settings_section( 'general', __( 'General', 'cmdr_plugin' ), 'cmdr_settings_general', 'cmdr_plugin' );
	add_settings_field( 'user_fields', __( 'User fields', 'cmdr_plugin' ), 'cmdr_settings_user_fields', 'cmdr_plugin', 'general' );

	add_settings_section( 'api', __( 'API', 'cmdr_plugin' ), 'cmdr_settings_api', 'cmdr_plugin' );
	add_settings_field( 'api_key', __( 'API key', 'cmdr_plugin' ), 'cmdr_settings_api_key', 'cmdr_plugin', 'api' );
	add_settings_field( 'list_id', __( 'List ID', 'cmdr_plugin' ), 'cmdr_settings_list_id', 'cmdr_plugin', 'api' );

	add_settings_section( 'cm', __( 'CM', 'cmdr_plugin' ), 'cmdr_settings_cm', 'cmdr_plugin' );
	add_settings_field( 'cm_sync', __( 'Keep in sync', 'cmdr_plugin' ), 'cmdr_settings_cm_sync', 'cmdr_plugin', 'cm' );
}

function cmdr_settings_sanitize( $cmdr_settings ) {
	$cmdr_settings[ 'sync_timestamp' ] = current_time( 'timestamp' );
	return $cmdr_settings;
}

function cmdr_settings_general() {
}

function cmdr_settings_user_fields() {
	global $wpdb;
	global $cmdr_fields_to_hide;

	$cmdr_settings = ( array ) get_option( 'cmdr_settings' );
	$cmdr_user_fields = ( array ) $cmdr_settings[ 'user_fields' ];
	
	// Get user meta keys
	$querystr = "
		SELECT DISTINCT umeta.meta_key
		FROM $wpdb->users as u, $wpdb->usermeta as umeta
		WHERE u.id = umeta.user_id
		ORDER BY umeta.meta_key
	";
	$items = $wpdb->get_results( $querystr, OBJECT );
	
	foreach( $items as $item ) {
		if ( in_array( $item->meta_key, $cmdr_fields_to_hide ) ) {
			continue;
		}
		echo '<label><input type="checkbox" name="cmdr_settings[user_fields][]" value="' . esc_attr( $item->meta_key ) . '" ' . checked( in_array( $item->meta_key, $cmdr_user_fields ), true, false ) . ' /> ' . $item->meta_key . '</label><br />';
	}
}

function cmdr_settings_api() {
}

function cmdr_settings_api_key() {
	$cmdr_settings = ( array ) get_option( 'cmdr_settings' );
	echo '<input type="text" name="cmdr_settings[api_key]" value="' . esc_attr( $cmdr_settings[ 'api_key'] ) . '" size="70" />';
}

function cmdr_settings_list_id() {
	$cmdr_settings = ( array ) get_option( 'cmdr_settings' );
	echo '<input type="text" name="cmdr_settings[list_id]" value="' . esc_attr( $cmdr_settings[ 'list_id'] ) . '" size="70" />';
}

function cmdr_settings_cm() {
}

function cmdr_settings_cm_sync() {
	$cmdr_settings = ( array ) get_option( 'cmdr_settings' );
	echo '<input type="hidden" name="cmdr_settings[cm_sync]" value="0" />';
	echo '<input type="checkbox" name="cmdr_settings[cm_sync]" value="1" ' . checked( $cmdr_settings[ 'cm_sync'], 1, false ) . ' />';
}

function cmdr_settings_menu() {
	add_options_page( __( 'Campaign Monitor Dual Registration Options', 'cmdr_plugin' ), __( 'CM Dual Registration', 'cmdr_plugin' ), 'manage_options', 'cmdr_plugin', 'cmdr_settings_page' );
}

function cmdr_settings_page() {
	?>
	<div class="wrap">
		<h2><?php _e( 'Campaign Monitor Dual Registration Options', 'cmdr_plugin' ); ?></h2>
		<form action="options.php" method="POST">
			<?php settings_fields( 'cmdr_settings_group' ); ?>
			<?php do_settings_sections( 'cmdr_plugin' ); ?>
			<?php submit_button( __( 'save and sync', 'cmdr_plugin' ) ); ?>
		</form>
	</div>
	<?php
}

function cmdr_init() {
	global $cmdr_fields_to_hide;
	
	$cmdr_fields_to_hide = array(
		'admin_color',
		'closedpostboxes_nav-menus',
		'comment_shortcuts',
		'dismissed_wp_pointers',
		'managenav-menuscolumnshidden',
		'metaboxhidden_nav-menus',
		'nav_menu_recently_edited',
		'rich_editing',
		'show_admin_bar_front',
		'show_welcome_panel',
		'use_ssl',
		'wp_capabilities',
		'wp_dashboard_quick_press_last_post_id',
		'wp_user-settings',
		'wp_user-settings-time',
		'wp_user_level',
		'session_tokens'
	);
	$cmdr_fields_to_hide = apply_filters( 'cmdr_edit_fileds_to_hide', $cmdr_fields_to_hide );
	
	// Backward compatibility
	$cmdr_settings = ( array ) get_option( 'cmdr_settings' );
	if ( get_option( 'cmdr_user_fields' ) ) {
		$cmdr_settings[ 'user_fields' ] = ( array ) unserialize( base64_decode( get_option( 'cmdr_user_fields' ) ) );
		update_option( 'cmdr_settings', $cmdr_settings );
		delete_option( 'cmdr_user_fields' );
	}
	if ( get_option( 'cmdr_api_key' ) ) {
		$cmdr_settings[ 'api_key' ] = get_option( 'cmdr_api_key' );
		update_option( 'cmdr_settings', $cmdr_settings );
		delete_option( 'cmdr_api_key' );
	}
	if ( get_option( 'cmdr_list_id' ) ) {
		$cmdr_settings[ 'list_id' ] = get_option( 'cmdr_list_id' );
		update_option( 'cmdr_settings', $cmdr_settings );
		delete_option( 'cmdr_list_id' );
	}
	if ( get_option( 'cmdr_cm_sync' ) ) {
		$cmdr_settings[ 'cm_sync' ] = get_option( 'cmdr_cm_sync' );
		update_option( 'cmdr_settings', $cmdr_settings );
		delete_option( 'cmdr_cm_sync' );
	}
}

function cmdr_user_update( $user_id, $old_user_data ) {
	$user = get_userdata( $user_id );
	if ( ! $user ) {
		return;
	}

	$args = array();
	if ( $user->user_email != $old_user_data->user_email ) {
		$args[ 'EmailAddress' ] = $user->user_email;
	}
	if ( $user->first_name != $user->first_name || $user->last_name != $user->last_name ) {
		$args[ 'Name' ] = $user->first_name . ' ' . $user->last_name;
	}

	if ( count( $args ) ) {
		// Make user sync
		CMDR_Dual_Synchronizer::cmdr_user_update( $user_id, $args, $old_user_data->user_email );
	}
}

function cmdr_save_and_sync( $cmdr_settings_old ) {
	$cmdr_settings = ( array ) get_option( 'cmdr_settings' );
	$cmdr_settings_old = ( array ) $cmdr_settings_old;

	// Handle the webhook if needed
	if ( $cmdr_settings[ 'cm_sync' ] != $cmdr_settings_old[ 'cm_sync' ] ) {
		if ( ! class_exists( 'CS_REST_Lists' ) ) {
			require_once CMDR_PLUGIN_PATH . 'campaignmonitor-createsend-php/csrest_lists.php';
		}

		$cmdr_api_key = $cmdr_settings[ 'api_key' ];
		$cmdr_list_id = $cmdr_settings[ 'list_id' ];
		$auth = array( 'api_key' => $cmdr_api_key );
		$wrap_l = new CS_REST_Lists( $cmdr_list_id, $auth );

		if ( $cmdr_settings[ 'cm_sync' ] ) {
			// Create the webhook
			$c = true;
			$result = $wrap_l->get_webhooks();
			if ( ! $result->was_successful() ) {
				add_settings_error( 'cmdr_settings', 'cm-error', __( $result->response->Message, 'cmdr_plugin' ) );
			}
			foreach( $result->response as $hook ) {
				if ( $hook->Url == admin_url( 'admin-ajax.php?action=cmdr-cm-sync' ) ) {
					$c = false;
					break;
				}
			}

			if ( $c ) {
				$result = $wrap_l->create_webhook( array(
					'Events' => array( CS_REST_LIST_WEBHOOK_SUBSCRIBE, CS_REST_LIST_WEBHOOK_UPDATE ),
					'Url' => admin_url( 'admin-ajax.php?action=cmdr-cm-sync' ),
					'PayloadFormat' => CS_REST_WEBHOOK_FORMAT_JSON
				) );
				if ( ! $result->was_successful() ) {
					add_settings_error( 'cmdr_settings', 'cm-error', __( $result->response->Message, 'cmdr_plugin' ) );
				}
			}
		} else {
			// Remove the webhook
			$c = false;
			$result = $wrap_l->get_webhooks();
			if ( ! $result->was_successful() ) {
				add_settings_error( 'cmdr_settings', 'cm-error', __( $result->response->Message, 'cmdr_plugin' ) );
			}
			foreach( $result->response as $hook ) {
				if ( $hook->Url == admin_url( 'admin-ajax.php?action=cmdr-cm-sync' ) ) {
					$c = $hook->WebhookID;
					break;
				}
			}

			if ( $c ) {
				$result = $wrap_l->delete_webhook( $c );
				if ( ! $result->was_successful() ) {
					add_settings_error( 'cmdr_settings', 'cm-error', __( $result->response->Message, 'cmdr_plugin' ) );
				}
			}
		}
	}
	
	// Make forced sync if needed
	if ( $cmdr_settings[ 'sync_timestamp' ] > $cmdr_settings_old[ 'sync_timestamp' ] ) {
		$result = CMDR_Dual_Synchronizer::cmdr_mass_update();
		if ( ! $result ) {
			add_settings_error( 'cmdr_settings', 'cm-error', __( CMDR_Dual_Synchronizer::$error->Message, 'cmdr_plugin' ) . ( ! empty( CMDR_Dual_Synchronizer::$error->ResultData ) ? '<br />' . __( 'Error details: ', 'cmdr_plugin' ) . json_encode( CMDR_Dual_Synchronizer::$error->ResultData ) : '' ) );
		}
	}
}

function cmdr_user_insert( $user_id ) {
	// Make new user sync
	CMDR_Dual_Synchronizer::cmdr_user_update( $user_id, null, null, true );
}

function cmdr_cm_sync() {
	global $cmdr_fields_to_hide;

	// Get plugin settings
	$cmdr_settings = ( array ) get_option( 'cmdr_settings' );
	$cmdr_list_id = $cmdr_settings[ 'list_id' ];
	$cmdr_user_fields = ( array ) $cmdr_settings[ 'user_fields' ];

	if ( ! class_exists( 'CS_REST_SERIALISATION_get_available' ) ) {
		require_once CMDR_PLUGIN_PATH . 'campaignmonitor-createsend-php/class/serialisation.php';
	}
	if ( ! class_exists( 'CS_REST_Log' ) ) {
		require_once CMDR_PLUGIN_PATH . 'campaignmonitor-createsend-php/class/log.php';
	}

	// Get a serialiser for the webhook data - We assume here that we're dealing with json
	$serialiser = CS_REST_SERIALISATION_get_available( new CS_REST_Log( CS_REST_LOG_NONE ) );

	// Read all the posted data from the input stream
	$raw_post = file_get_contents("php://input");

	// And deserialise the data
	$deserialised_data = $serialiser->deserialise( $raw_post );

	// List ID check
	$list_id = $deserialised_data->ListID;
	if ( trim( $list_id ) == trim( $cmdr_list_id ) ) {
		remove_action( 'profile_update', 'cmdr_user_update', 10 );
		remove_action( 'user_register', 'cmdr_user_insert' );
		remove_filter( 'update_user_metadata', 'cmdr_user_meta_update', 1000 );
		
		foreach( $deserialised_data->Events as $subscriber ) {
			if ( ! empty( $subscriber->OldEmailAddress ) ) {
				$user = get_user_by( 'email', $subscriber->OldEmailAddress );
			} else {
				$user = get_user_by( 'email', $subscriber->EmailAddress );
			}
			
			if ( $user ) {
				if ( $user->user_email != $subscriber->EmailAddress ) {
					wp_update_user( array ( 'ID' => $user->ID, 'user_email' => $subscriber->EmailAddress ) );
				}
				if ( $user->first_name . ' ' . $user->last_name != $subscriber->Name ) {
					$n = explode( ' ', $subscriber->Name );
					$fn = array_shift( $n );
					$ln = implode( ' ', $n );
					if ( $fn ) {
						update_user_meta( $user->ID, 'first_name', $fn );
					}
					if ( $ln ) {
						update_user_meta( $user->ID, 'last_name', $ln );
					}
					foreach( $subscriber->CustomFields as $key => $field ) {
						if ( in_array( $field->Key, $cmdr_user_fields ) && ! in_array( $field->Key, $cmdr_fields_to_hide ) ) {
							update_user_meta( $user->ID, $field->Key, $field->Value );
						}
					}
				}
			}
		}
	}
	
	echo 'ok';
	die();
}

function cmdr_user_meta_update( $temp, $user_id, $meta_key, $meta_value ) {
	global $cmdr_fields_to_hide;
	
	$cmdr_settings = ( array ) get_option( 'cmdr_settings' );
	$cmdr_user_fields = ( array ) $cmdr_settings[ 'user_fields' ];
	
	// The same value, no needs to update
	if ( $meta_value == get_user_meta( $user_id, $meta_key, true ) )
		return;
	
	// Field should not be updated
	if ( ! in_array( $meta_key, $cmdr_user_fields ) || in_array( $meta_key, $cmdr_fields_to_hide ) )
		return;

	$args = array();
	$args[ 'CustomFields' ][] = array( 'Key' => $meta_key, 'Value' => $meta_value );
	CMDR_Dual_Synchronizer::cmdr_user_update( $user_id, $args );
}
