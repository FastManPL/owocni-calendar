<?php
/**
 * Plugin Name: Owocni Calendar Widget for Elementor
 * Description: Kalendrz od Owocnych
 * Version: 1.1.1
 * Author: Dawid Nowak / Owocni.pl
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; 
}


function owocni_register_calendar_cpt() {
    $labels = array(
        'name'                  => _x( 'Kalendarze', 'Post Type General Name', 'owocni-calendar' ),
        'singular_name'         => _x( 'Kalendarz', 'Post Type Singular Name', 'owocni-calendar' ),
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
    <label for="owocni_calendar_offset">Offset wizyty (HH:MM), określenie za jaki czas od obecnej godziny można rezerwować najbliższą wizytę:</label>
    <input type="time" id="owocni_calendar_offset" name="owocni_calendar_offset" value="<?php echo isset($settings['offset']) ? $settings['offset'] : '00:00'; ?>"><br><br>
    <label for="owocni_calendar_reservation_limit">Czas trwania rezerwacji (HH:MM), określenie po jakim czasie nieopłacony termin jest zwalniany</label>
    <input type="time" id="owocni_calendar_reservation_limit" name="owocni_calendar_reservation_limit" value="<?php echo isset($settings['reservation_limit']) ? $settings['reservation_limit'] : '00:00'; ?>"><br><br>
    <label for="owocni_calendar_visit_price">Cena wizyty (PLN):</label>
    <input type="number" id="owocni_calendar_visit_price" name="owocni_calendar_visit_price" value="<?php echo isset($settings['visit_price']) ? $settings['visit_price'] : '100'; ?>"><br><br>
    <label for="owocni_calendar_return_url">URL powrotu po płatności (podziękowanie):</label>
    <input type="text" id="owocni_calendar_return_url" style="width:100%;" name="owocni_calendar_return_url" value="<?php echo isset($settings['return_url']) ? $settings['return_url'] : '' ?>"><br><br>
    
    
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
    $settings['offset'] = isset($_POST['owocni_calendar_offset']) ? sanitize_text_field($_POST['owocni_calendar_offset']) : '00:00'; 
    $settings['reservation_limit'] = isset($_POST['owocni_calendar_reservation_limit']) ? sanitize_text_field($_POST['owocni_calendar_reservation_limit']) : '00:00';
    $settings['visit_price'] = isset($_POST['owocni_calendar_visit_price']) ? sanitize_text_field($_POST['owocni_calendar_visit_price']) : '0';
    $settings['return_url'] = isset($_POST['owocni_calendar_return_url']) ? sanitize_text_field($_POST['owocni_calendar_return_url']) : home_url('/');
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
        'owocni-calendar-js',
        plugin_dir_url( __FILE__ ) . 'assets/script.js',
        [ 'jquery' ],
        '1.0.0',
        true
    );
}
add_action( 'wp_enqueue_scripts', 'custom_elementor_widgets_enqueue_styles' );


function register_rezerwacja_post_type() {
    $labels = array(
        'name'                  => _x( 'Rezerwacje', 'Post Type General Name', 'text_domain' ),
        'singular_name'         => _x( 'Rezerwacja', 'Post Type Singular Name', 'text_domain' ),
        'menu_name'             => __( 'Rezerwacje', 'text_domain' ),
        'name_admin_bar'        => __( 'Rezerwacja', 'text_domain' ),
        'archives'              => __( 'Archiwum rezerwacji', 'text_domain' ),
        'attributes'            => __( 'Atrybuty rezerwacji', 'text_domain' ),
        'parent_item_colon'     => __( 'Nadrzędna rezerwacja:', 'text_domain' ),
        'all_items'             => __( 'Wszystkie rezerwacje', 'text_domain' ),
        'add_new_item'          => __( 'Dodaj nową rezerwację', 'text_domain' ),
        'add_new'               => __( 'Dodaj nową', 'text_domain' ),
        'new_item'              => __( 'Nowa rezerwacja', 'text_domain' ),
        'edit_item'             => __( 'Edytuj rezerwację', 'text_domain' ),
        'update_item'           => __( 'Aktualizuj rezerwację', 'text_domain' ),
        'view_item'             => __( 'Zobacz rezerwację', 'text_domain' ),
        'view_items'            => __( 'Zobacz rezerwacje', 'text_domain' ),
        'search_items'          => __( 'Szukaj rezerwacji', 'text_domain' ),
        'not_found'             => __( 'Nie znaleziono rezerwacji', 'text_domain' ),
        'not_found_in_trash'    => __( 'Nie znaleziono rezerwacji w koszu', 'text_domain' ),
        'featured_image'        => __( 'Zdjęcie wyróżniające', 'text_domain' ),
        'set_featured_image'    => __( 'Ustaw zdjęcie wyróżniające', 'text_domain' ),
        'remove_featured_image' => __( 'Usuń zdjęcie wyróżniające', 'text_domain' ),
        'use_featured_image'    => __( 'Użyj jako zdjęcia wyróżniającego', 'text_domain' ),
        'insert_into_item'      => __( 'Wstaw do rezerwacji', 'text_domain' ),
        'uploaded_to_this_item' => __( 'Przesłano do tej rezerwacji', 'text_domain' ),
        'items_list'            => __( 'Lista rezerwacji', 'text_domain' ),
        'items_list_navigation' => __( 'Nawigacja po liście rezerwacji', 'text_domain' ),
        'filter_items_list'     => __( 'Filtruj listę rezerwacji', 'text_domain' ),
    );
    $args = array(
        'label'                 => __( 'Rezerwacja', 'text_domain' ),
        'description'           => __( 'Rezerwacje terminów', 'text_domain' ),
        'labels'                => $labels,
        'supports'              => array( 'title' ), 
        'taxonomies'            => array(),
        'hierarchical'          => false,
        'public'                => false, 
        'show_ui'               => true,
        'show_in_menu'          => true,
        'menu_position'         => 5,
        'menu_icon'             => 'dashicons-calendar-alt',
        'show_in_admin_bar'     => true,
        'show_in_nav_menus'     => false,
        'can_export'            => true,
        'has_archive'           => false,
        'exclude_from_search'   => true,
        'publicly_queryable'    => false,
        'capability_type'       => 'post',
        'show_in_rest'          => false,
    );
    register_post_type( 'rezerwacja', $args );
}
add_action( 'init', 'register_rezerwacja_post_type', 0 );

function add_rezerwacja_meta_boxes() {
    add_meta_box(
        'rezerwacja_dane',
        __( 'Dane rezerwacji', 'text_domain' ),
        'render_rezerwacja_meta_box',
        'rezerwacja',
        'normal',
        'high'
    );
}
add_action( 'add_meta_boxes', 'add_rezerwacja_meta_boxes' );


function render_rezerwacja_meta_box( $post ) {
    wp_nonce_field( 'rezerwacja_meta_box_nonce', 'rezerwacja_meta_box_nonce' );

    $data = get_post_meta( $post->ID, 'data', true );
    $godzina_od = get_post_meta( $post->ID, 'godzina_od', true );
    $godzina_do = get_post_meta( $post->ID, 'godzina_do', true );
    $wybrany_kalendarz = get_post_meta( $post->ID, 'wybrany_kalendarz', true );
        $imie_nazwisko = get_post_meta( $post->ID, 'imie_nazwisko', true );
        $dodatkowe_informacje = get_post_meta( $post->ID, 'dodatkowe_informacje', true );
        $kwota = get_post_meta( $post->ID, 'kwota', true );
        $platnosc = get_post_meta( $post->ID, 'platnosc', true );
        $email = get_post_meta( $post->ID, 'email', true );
        $telefon = get_post_meta( $post->ID, 'telefon', true );

    ?>
    <label for="data"><?php _e( 'Data', 'text_domain' ); ?></label><br>
    <input type="date" id="data" name="data" value="<?php echo esc_attr( $data ); ?>"><br><br>

    <label for="godzina_od"><?php _e( 'Godzina od', 'text_domain' ); ?></label><br>
    <input type="time" id="godzina_od" name="godzina_od" value="<?php echo esc_attr( $godzina_od ); ?>"><br><br>

    <label for="godzina_do"><?php _e( 'Godzina do', 'text_domain' ); ?></label><br>
    <input type="time" id="godzina_do" name="godzina_do" value="<?php echo esc_attr( $godzina_do ); ?>"><br><br>

    <label for="wybrany_kalendarz"><?php _e( 'Wybrany kalendarz', 'text_domain' ); ?></label><br>
<select id="wybrany_kalendarz" name="wybrany_kalendarz">
    <?php
    $kalendarze = get_posts(array(
        'post_type' => 'owocni_calendar', 
        'posts_per_page' => -1, 
        'orderby' => 'title',
        'order' => 'ASC',
    ));

    if ($kalendarze) { 
        foreach ($kalendarze as $kalendarz) {
            $selected = ($wybrany_kalendarz == $kalendarz->ID) ? 'selected' : '';
            echo '<option value="' . $kalendarz->ID . '" ' . $selected . '>' . esc_html($kalendarz->post_title) . '</option>';
        }
    } else {
                echo '<option value="">Brak kalendarzy</option>';
        }
    ?>
</select><br><br>

                <label for="imie_nazwisko"><?php _e( 'Imię i nazwisko', 'text_domain' ); ?></label><br>
    <input type="text" id="imie_nazwisko" style="width:100%;" name="imie_nazwisko" value="<?php echo esc_attr( $imie_nazwisko ); ?>"><br><br>

                <label for="dodatkowe_informacje"><?php _e( 'Dodatkowe informacje', 'text_domain' ); ?></label><br>
    <textarea id="dodatkowe_informacje" style="width:100%;" name="dodatkowe_informacje"><?php echo esc_textarea( $dodatkowe_informacje ); ?></textarea><br><br>

                <label for="kwota"><?php _e( 'Kwota (w groszach)', 'text_domain' ); ?></label><br>
    <input type="number" step="0.01" id="kwota" name="kwota" value="<?php echo esc_attr( $kwota ); ?>"><br><br>

    <label for="platnosc"><?php _e( 'Informacje o płatności', 'text_domain' ); ?></label><br>
    <input type="text" id="platnosc" style="width:100%;" name="platnosc" value="<?php echo esc_attr( $platnosc ); ?>"><br><br>

                <label for="email"><?php _e( 'Email', 'text_domain' ); ?></label><br>
    <input type="email" id="email" style="width:100%;" name="email" value="<?php echo esc_attr( $email ); ?>"><br><br>

                <label for="telefon"><?php _e( 'Telefon', 'text_domain' ); ?></label><br>
    <input type="text" id="telefon" style="width:100%;" name="telefon" value="<?php echo esc_attr( $telefon ); ?>"><br><br>
    <?php
}

function save_rezerwacja_meta_box_data( $post_id ) {
    if ( ! isset( $_POST['rezerwacja_meta_box_nonce'] ) || ! wp_verify_nonce( $_POST['rezerwacja_meta_box_nonce'], 'rezerwacja_meta_box_nonce' ) ) {
        return;
    }

    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    if ( isset( $_POST['post_type'] ) && 'rezerwacja' == $_POST['post_type'] ) {
        if ( ! current_user_can( 'edit_post', $post_id ) ) { 
            return;
        }
    } else {
        return;
    }

        $fields = array('data', 'godzina_od', 'godzina_do', 'wybrany_kalendarz', 'imie_nazwisko', 'dodatkowe_informacje', 'kwota', 'platnosc', 'email', 'telefon');

        foreach ($fields as $field) {
                if ( isset( $_POST[$field] ) ) {
                        update_post_meta( $post_id, $field, sanitize_text_field( $_POST[$field] ) );
                }
        }
}
add_action( 'save_post', 'save_rezerwacja_meta_box_data' );

add_action( 'admin_post_zapisz_rezerwacje', 'zapisz_rezerwacje' );
function zapisz_rezerwacje() {
    if ( isset( $_POST['action'] ) && $_POST['action'] == 'zapisz_rezerwacje' ) {
                $data = sanitize_text_field($_POST['data']);
                $godzina_od = sanitize_text_field($_POST['godzina_od']);
        $godzina_do = sanitize_text_field($_POST['godzina_do']);
        $wybrany_kalendarz = sanitize_text_field($_POST['wybrany_kalendarz']);
        $imie_nazwisko = sanitize_text_field($_POST['imie_nazwisko']);
        $dodatkowe_informacje = sanitize_textarea_field($_POST['dodatkowe_informacje']);
        $email = sanitize_email($_POST['email']);
        $telefon = sanitize_text_field($_POST['telefon']);

        $post_title = $data . ' ' . $godzina_od . ' - ' . $godzina_do;

        $post_id = wp_insert_post( array(
            'post_type' => 'rezerwacja',
            'post_status' => 'publish',
                        'post_title' => $post_title
        ));

        if ($post_id) {
                        update_post_meta($post_id, 'data', $data);
                        update_post_meta($post_id, 'godzina_od', $godzina_od);
                        update_post_meta($post_id, 'godzina_do', $godzina_do);
                        update_post_meta($post_id, 'wybrany_kalendarz', $wybrany_kalendarz);
                        update_post_meta($post_id, 'imie_nazwisko', $imie_nazwisko);
                        update_post_meta($post_id, 'dodatkowe_informacje', $dodatkowe_informacje);
                        update_post_meta($post_id, 'email', $email);
                        update_post_meta($post_id, 'telefon', $telefon);

            wp_redirect( wp_get_referer() ); 
            exit;
        } else {
            wp_die('Błąd podczas zapisywania rezerwacji.');
        }
    }
}


function update_payment_info_display($post_id) {
    $p24_status = get_post_meta($post_id, 'p24_status', true);
    $p24_session_id = get_post_meta($post_id, 'p24_session_id', true);
    $p24_order_id = get_post_meta($post_id, 'p24_order_id', true);
    $kwota = get_post_meta($post_id, 'kwota', true);
    $payment_info = '';
    if ($p24_status) {
        if ($p24_status == 'completed' || $p24_status == 'paid') {
            $payment_info = 'Status: OPŁACONE';
        } elseif ($p24_status == 'pending') {
            $payment_info = 'Status: OCZEKUJE NA PŁATNOŚĆ';
        } elseif ($p24_status == 'failed') {
            $payment_info = 'Status: PŁATNOŚĆ ODRZUCONA';
        } else {
            $payment_info = 'Status: ' . $p24_status;
        }
        if ($kwota) {
            $payment_info .= ' | Kwota: ' . number_format($kwota/100, 2, ',', ' ') . ' PLN';
        }
        if ($p24_session_id) {
            $payment_info .= ' | ID sesji: ' . $p24_session_id;
        }
        if ($p24_order_id) {
            $payment_info .= ' | ID zamówienia: ' . $p24_order_id;
        }
        update_post_meta($post_id, 'platnosc', $payment_info);
    } else if ($p24_session_id) {
        $payment_info = 'Status: OCZEKUJE NA PŁATNOŚĆ';
        if ($kwota) {
            $payment_info .= ' | Kwota: ' . number_format($kwota/100, 2, ',', ' ') . ' PLN';
        }
        if ($p24_session_id) {
            $payment_info .= ' | ID sesji: ' . $p24_session_id;
        }
        update_post_meta($post_id, 'platnosc', $payment_info);
    }
}

add_action('save_post_rezerwacja', 'update_payment_info_after_save', 20, 3);
function update_payment_info_after_save($post_id, $post, $update) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (!$update) {
        return;
    }
    update_payment_info_display($post_id);
}

require_once('includes/przelewy24-settings.php');
