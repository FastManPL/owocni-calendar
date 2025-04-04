<?php
defined( 'ABSPATH' ) || exit;

add_action('admin_menu', 'owocni_calendar_options_page');
function owocni_calendar_options_page() {
    add_options_page(
        'Ustawienia Kalendarza Owocni',
        'Kalendarz Owocni',
        'manage_options',
        'owocni-calendar-settings',
        'owocni_calendar_przelewy24_settings_callback' 
    );
}

function owocni_calendar_przelewy24_settings_callback() {
    ?>
    <div class="wrap">
        <h1>Ustawienia Kalendarza Owocni</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('owocni-calendar-settings-group'); 
            do_settings_sections('owocni-calendar-settings');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

add_action('admin_init', 'owocni_calendar_register_settings');
function owocni_calendar_register_settings() {
    register_setting('owocni-calendar-settings-group', 'owocni_calendar_p24_mode', array('default' => 'sandbox')); 
    register_setting('owocni-calendar-settings-group', 'owocni_calendar_p24_merchant_id'); 
    register_setting('owocni-calendar-settings-group', 'owocni_calendar_p24_api_key'); 
    register_setting('owocni-calendar-settings-group', 'owocni_calendar_p24_pos_id');
    register_setting('owocni-calendar-settings-group', 'owocni_calendar_p24_crc'); 
    add_settings_section('owocni_calendar_p24_settings_section', 'Ustawienia Przelewy24', null, 'owocni-calendar-settings');
    add_settings_field('owocni_calendar_p24_mode', 'Tryb Przelewy24', 'owocni_calendar_p24_mode_callback', 'owocni-calendar-settings', 'owocni_calendar_p24_settings_section');
    add_settings_field('owocni_calendar_p24_merchant_id', 'ID Sprzedawcy (Merchant ID)', 'owocni_calendar_p24_merchant_id_callback', 'owocni-calendar-settings', 'owocni_calendar_p24_settings_section');
    add_settings_field('owocni_calendar_p24_api_key', 'API Key (raporty)', 'owocni_calendar_p24_api_key_callback', 'owocni-calendar-settings', 'owocni_calendar_p24_settings_section');
    add_settings_field('owocni_calendar_p24_pos_id', 'ID Punktu Sprzedaży (POS ID)', 'owocni_calendar_p24_pos_id_callback', 'owocni-calendar-settings', 'owocni_calendar_p24_settings_section');
    add_settings_field('owocni_calendar_p24_crc', 'Klucz CRC', 'owocni_calendar_p24_crc_callback', 'owocni-calendar-settings', 'owocni_calendar_p24_settings_section');

}

function owocni_calendar_p24_mode_callback() {
    $mode = get_option('owocni_calendar_p24_mode', 'sandbox');
    ?>
    <select name="owocni_calendar_p24_mode">
        <option value="sandbox" <?php selected($mode, 'sandbox'); ?>>Sandbox</option>
        <option value="production" <?php selected($mode, 'production'); ?>>Produkcja</option>
    </select>
    <?php
}

function owocni_calendar_p24_merchant_id_callback() {
    $merchant_id = get_option('owocni_calendar_p24_merchant_id');
    ?>
    <input type="text" name="owocni_calendar_p24_merchant_id" value="<?php echo esc_attr($merchant_id); ?>" />
    <?php
}
function owocni_calendar_p24_api_key_callback() {
    $api_key = get_option('owocni_calendar_p24_api_key');
    ?>
    <input type="text" name="owocni_calendar_p24_api_key" value="<?php echo esc_attr($api_key); ?>" />
    <?php
}
function owocni_calendar_p24_pos_id_callback() {
    $pos_id = get_option('owocni_calendar_p24_pos_id');
    ?>
    <input type="text" name="owocni_calendar_p24_pos_id" value="<?php echo esc_attr($pos_id); ?>" />
    <?php
}
function owocni_calendar_p24_crc_callback() {
    $crc = get_option('owocni_calendar_p24_crc');
    ?>
    <input type="text" name="owocni_calendar_p24_crc" value="<?php echo esc_attr($crc); ?>" />
    <?php
}


add_action('admin_post_p24_status_update', 'owocni_calendar_p24_status_update_callback');
add_action('admin_post_nopriv_p24_status_update', 'owocni_calendar_p24_status_update_callback');

function owocni_calendar_p24_status_update_callback() {
    $input_data = file_get_contents('php://input');
    $json_data = json_decode($input_data, true);
    if (!isset($_GET['rezerwacja_id']) || empty($_GET['rezerwacja_id'])) {
        error_log('P24 Callback: Brak ID rezerwacji.');
        wp_die('Brak ID rezerwacji.');
    }
    $rezerwacja_id = intval($_GET['rezerwacja_id']);
    $current_status = get_post_meta($rezerwacja_id, 'p24_status', true);
    if ($current_status === 'completed') {
        exit('OK - Płatność już potwierdzona');
    }
    $merchantId = get_option('owocni_calendar_p24_merchant_id');
    $apiKey = get_option('owocni_calendar_p24_api_key');
    $posId = get_option('owocni_calendar_p24_pos_id');
    $crc = get_option('owocni_calendar_p24_crc');
    $p24Mode = get_option('owocni_calendar_p24_mode', 'sandbox');
    $isSandbox = ($p24Mode === 'sandbox');
    $sessionId = get_post_meta($rezerwacja_id, 'p24_session_id', true);
    $kwota = get_post_meta($rezerwacja_id, 'kwota', true);
    if (!empty($json_data)) {
        $orderId = isset($json_data['orderId']) ? $json_data['orderId'] : null;
        $amount = isset($json_data['amount']) ? $json_data['amount'] : null;
        $currency = isset($json_data['currency']) ? $json_data['currency'] : 'PLN';
        $json_session_id = isset($json_data['sessionId']) ? $json_data['sessionId'] : null;
        if ($json_session_id && $sessionId && $json_session_id === $sessionId) {
            update_post_meta($rezerwacja_id, 'p24_status', 'completed');
            update_post_meta($rezerwacja_id, 'p24_order_id', $orderId);
            $payment_info = 'Status: OPŁACONE | Kwota: ' . number_format($amount/100, 2, ',', ' ') . ' PLN';
            $payment_info .= ' | ID sesji: ' . $sessionId;
            $payment_info .= ' | ID zamówienia: ' . $orderId;
            update_post_meta($rezerwacja_id, 'platnosc', $payment_info);
            $current_title = get_the_title($rezerwacja_id);
            $clean_title = preg_replace('/ - (OPŁACONE|ODRZUCONE)( - (OPŁACONE|ODRZUCONE))*$/', '', $current_title);
            wp_update_post(array(
                'ID' => $rezerwacja_id,
                'post_title' => $clean_title . ' - OPŁACONE'
            ));
            if ($current_status !== 'completed') {
                $email = get_post_meta($rezerwacja_id, 'email', true);
                $imie_nazwisko = get_post_meta($rezerwacja_id, 'imie_nazwisko', true);
                $data = get_post_meta($rezerwacja_id, 'data', true);
                $godzina_od = get_post_meta($rezerwacja_id, 'godzina_od', true);
                $godzina_do = get_post_meta($rezerwacja_id, 'godzina_do', true);
                $subject = get_bloginfo('name') . ' - Potwierdzenie rezerwacji i płatności ';
                $message = "Witaj $imie_nazwisko,\n\n";
                $message .= "Twoja rezerwacja na dzień $data w godzinach $godzina_od - $godzina_do została opłacona i potwierdzona.\n";
                $message .= "Dziękujemy za dokonanie rezerwacji!\n\n";
                $message .= "Pozdrawiamy,\n";
                $message .= get_bloginfo('name');
                wp_mail($email, $subject, $message);
            }
            exit('OK');
        }
    }
    
    if (empty($sessionId)) {
        wp_die('Brak ID sesji w rezerwacji.');
    }
    if ($current_status !== 'completed') {
        update_post_meta($rezerwacja_id, 'p24_status', 'completed');
        $payment_info = 'Status: OPŁACONE';
        if ($kwota) {
            $payment_info .= ' | Kwota: ' . number_format($kwota/100, 2, ',', ' ') . ' PLN';
        }
        $payment_info .= ' | ID sesji: ' . $sessionId;
        $payment_info .= ' | Uwaga: Status zaktualizowany automatycznie';
        update_post_meta($rezerwacja_id, 'platnosc', $payment_info);
        $current_title = get_the_title($rezerwacja_id);
        $clean_title = preg_replace('/ - (OPŁACONE|ODRZUCONE)( - (OPŁACONE|ODRZUCONE))*$/', '', $current_title);
        wp_update_post(array(
            'ID' => $rezerwacja_id,
            'post_title' => $clean_title . ' - OPŁACONE'
        ));
    }
    
    exit('OK');
}