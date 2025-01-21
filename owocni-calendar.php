<?php
/**
 * Plugin Name: Owocni Calendar Widget for Elementor
 * Description: Kalendrz od Owocnych
 * Version: 1.0.0
 * Author: Dawid Nowak / Owocni.pl
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; 
}


function owocni_register_calendar_cpt() {
    $labels = array(
        'name'                  => _x( 'Kalendarze', 'Post Type General Name', 'owocni-calendar' ),
        'singular_name'         => _x( 'Kalendarz', 'Post Type Singular Name', 'owocni-calendar' ),
        // ... inne etykiety
    );
    $args = array(
        'label'               => __( 'Kalendarze', 'owocni-calendar' ),
        'description'         => __( 'Kalendarze rezerwacji', 'owocni-calendar' ),
        'labels'              => $labels,
        'supports'            => array( 'title' ),
        'taxonomies'          => array(),
        'hierarchical'        => false,
        'public'              => false,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'menu_position'       => 5,
        'show_in_admin_bar'   => true,
        'show_in_nav_menus'   => true,
        'can_export'          => true,
        'has_archive'         => false,
        'exclude_from_search' => true,
        'publicly_queryable'  => false,
        'capability_type'     => 'post',
        'show_in_rest'       => true,
    );
    register_post_type( 'owocni_calendar', $args );
}
add_action( 'init', 'owocni_register_calendar_cpt' );

// Meta Boxy dla ustawień kalendarza
function owocni_calendar_metaboxes() {
    add_meta_box( 'owocni_calendar_settings', 'Ustawienia Kalendarza', 'owocni_calendar_settings_callback', 'owocni_calendar', 'normal', 'high' );
}
add_action( 'add_meta_boxes', 'owocni_calendar_metaboxes' );

function owocni_calendar_settings_callback( $post ) {
    wp_nonce_field( 'owocni_calendar_settings_nonce', 'owocni_calendar_settings_nonce' );
    $days = ['poniedziałek', 'wtorek', 'środa', 'czwartek', 'piątek', 'sobota', 'niedziela'];
    $settings = get_post_meta( $post->ID, '_owocni_calendar_settings', true );
    if (empty($settings)){
        $settings = [];
    }
    ?>
    <label for="owocni_calendar_interval">Interwał wizyt (minuty):</label>
    <input type="number" id="owocni_calendar_interval" name="owocni_calendar_interval" value="<?php echo isset($settings['interval']) ? $settings['interval'] : '30'; ?>"><br><br>
    <?php foreach ($days as $day): ?>
        <h3><?php echo $day; ?></h3>
        <label for="owocni_calendar_start_<?php echo $day; ?>">Godzina rozpoczęcia:</label>
        <input type="time" id="owocni_calendar_start_<?php echo $day; ?>" name="owocni_calendar_start_<?php echo $day; ?>" value="<?php echo isset($settings['start'][$day]) ? $settings['start'][$day] : '09:00'; ?>"><br>
        <label for="owocni_calendar_end_<?php echo $day; ?>">Godzina zakończenia:</label>
        <input type="time" id="owocni_calendar_end_<?php echo $day; ?>" name="owocni_calendar_end_<?php echo $day; ?>" value="<?php echo isset($settings['end'][$day]) ? $settings['end'][$day] : '17:00'; ?>"><br><br>
    <?php endforeach; ?>
    <?php
}

function owocni_save_calendar_settings( $post_id ) {
    if ( ! isset( $_POST['owocni_calendar_settings_nonce'] ) || ! wp_verify_nonce( $_POST['owocni_calendar_settings_nonce'], 'owocni_calendar_settings_nonce' ) ) {
        return;
    }

    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    if ( isset( $_POST['post_type'] ) && 'owocni_calendar' == $_POST['post_type'] ) {
        if ( ! current_user_can( 'edit_page', $post_id ) ) {
            return;
        }
    } else {
        return;
    }

    $settings = array();
    $settings['interval'] = isset($_POST['owocni_calendar_interval']) ? sanitize_text_field($_POST['owocni_calendar_interval']) : '30';
    $days = ['poniedziałek', 'wtorek', 'środa', 'czwartek', 'piątek', 'sobota', 'niedziela'];
    foreach ($days as $day){
        $settings['start'][$day] = isset($_POST['owocni_calendar_start_' . $day]) ? sanitize_text_field($_POST['owocni_calendar_start_' . $day]) : '09:00';
        $settings['end'][$day] = isset($_POST['owocni_calendar_end_' . $day]) ? sanitize_text_field($_POST['owocni_calendar_end_' . $day]) : '17:00';
    }
    update_post_meta( $post_id, '_owocni_calendar_settings', $settings );
}
add_action( 'save_post', 'owocni_save_calendar_settings' );


function custom_elementor_widgets_register( $widgets_manager ) {
    require_once plugin_dir_path( __FILE__ ) . 'widgets/owocni-calendar-widget.php';
    $widgets_manager->register( new \Owocni_Calendar_Widget() );
}
add_action( 'elementor/widgets/register', 'custom_elementor_widgets_register' );

function owocni_register_elementor_category( $elements_manager ) {
    $elements_manager->add_category(
        'owocni-calendar',
        [
            'title' => __( 'Owocni Calendar Widgets', 'owocni-calendar' ),
            'icon'  => 'eicon-woocommerce',
        ]
    );
}
add_action( 'elementor/elements/categories_registered', 'owocni_register_elementor_category' );

function custom_elementor_widgets_enqueue_styles() {
    wp_enqueue_style(
        'owocni-calendar-css',
        plugin_dir_url( __FILE__ ) . 'assets/style.css'
    );

    wp_enqueue_script(
        'owocni-calendar-ajax',
        plugin_dir_url( __FILE__ ) . 'assets/script-ajax.js',
        [ 'jquery' ],
        '1.0.0',
        true
    );

    wp_enqueue_script(
        'owocni-calendar-js',
        plugin_dir_url( __FILE__ ) . 'assets/script.js',
        [ 'jquery' ],
        '1.0.0',
        true
    );
}
add_action( 'wp_enqueue_scripts', 'custom_elementor_widgets_enqueue_styles' );


