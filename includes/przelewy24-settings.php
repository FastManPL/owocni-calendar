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

//ponizej obsługa płatności przelewy24

add_action('admin_post_p24_status_update', 'owocni_calendar_p24_status_update_callback');
add_action('admin_post_nopriv_p24_status_update', 'owocni_calendar_p24_status_update_callback');


function owocni_calendar_p24_status_update_callback() {
    // 1. Sprawdzenie, czy przekazano ID rezerwacji
    if (!isset($_GET['rezerwacja_id']) || empty($_GET['rezerwacja_id'])) {
        error_log('P24 Callback: Brak ID rezerwacji.');
        wp_die('Brak ID rezerwacji.');
    }

    $rezerwacja_id = intval($_GET['rezerwacja_id']);
    error_log('P24 Callback: Przetwarzanie rezerwacji ID ' . $rezerwacja_id);

    // 2. Pobieranie ustawień P24
    $merchantId = get_option('owocni_calendar_p24_merchant_id');
    $apiKey = get_option('owocni_calendar_p24_api_key');
    $posId = get_option('owocni_calendar_p24_pos_id');
    $crc = get_option('owocni_calendar_p24_crc');
    $p24Mode = get_option('owocni_calendar_p24_mode', 'sandbox');
    $isSandbox = ($p24Mode === 'sandbox');

    // 3. Pobieranie danych z POST (od Przelewy24)
    if (empty($_POST)) {
        error_log('P24 Callback: Brak danych POST.');
        wp_die('Brak danych z Przelewy24.');
    }

    error_log('P24 Callback dane POST: ' . print_r($_POST, true));

    $sessionId = get_post_meta($rezerwacja_id, 'p24_session_id', true);
    $orderId = isset($_POST['p24_order_id']) ? $_POST['p24_order_id'] : null;
    $amount = isset($_POST['p24_amount']) ? $_POST['p24_amount'] : null;
    $currency = isset($_POST['p24_currency']) ? $_POST['p24_currency'] : 'PLN';

    if (empty($sessionId) || empty($orderId) || empty($amount)) {
        error_log('P24 Callback: Brak wymaganych danych. SessionID: ' . $sessionId . ', OrderID: ' . $orderId . ', Amount: ' . $amount);
        wp_die('Brak wymaganych danych z Przelewy24 lub w bazie danych.');
    }

    // 4. Obliczanie sumy kontrolnej
    $sign = hash('sha384', json_encode([
        'sessionId' => $sessionId,
        'orderId' => $orderId,
        'amount' => $amount,
        'currency' => $currency,
        'crc' => $crc
    ]));

    // 5. Przygotowanie danych do weryfikacji
    $verifyData = array(
        'merchantId' => intval($merchantId),
        'posId' => intval($posId),
        'sessionId' => $sessionId,
        'amount' => intval($amount),
        'currency' => $currency,
        'orderId' => $orderId,
        'sign' => $sign
    );

    // 6. Wysyłanie żądania weryfikującego do API Przelewy24
    $url = $isSandbox ? 'https://sandbox.przelewy24.pl/api/v1/transaction/verify' : 'https://secure.przelewy24.pl/api/v1/transaction/verify';

    $response = wp_remote_post($url, array(
        'headers' => array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Basic ' . base64_encode($merchantId . ':' . $apiKey)
        ),
        'body' => json_encode($verifyData),
        'timeout' => 45,
        'sslverify' => $isSandbox ? false : true
    ));

    if (is_wp_error($response)) {
        error_log('P24 Callback: Błąd weryfikacji - ' . $response->get_error_message());
        wp_die('Błąd weryfikacji płatności.');
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    error_log('P24 Callback odpowiedź: ' . print_r($body, true));

    if (isset($body['data']['status']) && $body['data']['status'] === 'success') {
        // Płatność OK
        update_post_meta($rezerwacja_id, 'p24_status', 'completed');
        update_post_meta($rezerwacja_id, 'p24_order_id', $orderId);
        
        $current_title = get_the_title($rezerwacja_id);
        // Usuń istniejący status z tytułu jeśli istnieje
        $clean_title = preg_replace('/ - (OPŁACONE|ODRZUCONE)$/', '', $current_title);
        
        wp_update_post(array(
            'ID' => $rezerwacja_id,
            'post_title' => $clean_title . ' - OPŁACONE'
        ));
        
        // Aktualizuj pole płatności
        update_payment_info_display($rezerwacja_id);
        
        error_log('P24 Callback: Płatność zatwierdzona dla rezerwacji ID ' . $rezerwacja_id);
        
        // Wyślij e-mail z potwierdzeniem
        $email = get_post_meta($rezerwacja_id, 'email', true);
        $imie_nazwisko = get_post_meta($rezerwacja_id, 'imie_nazwisko', true);
        $data = get_post_meta($rezerwacja_id, 'data', true);
        $godzina_od = get_post_meta($rezerwacja_id, 'godzina_od', true);
        $godzina_do = get_post_meta($rezerwacja_id, 'godzina_do', true);
        
        $subject = 'Potwierdzenie rezerwacji i płatności';
        $message = "Witaj $imie_nazwisko,\n\n";
        $message .= "Twoja rezerwacja na dzień $data w godzinach $godzina_od - $godzina_do została opłacona i potwierdzona.\n";
        $message .= "Dziękujemy za dokonanie rezerwacji!\n\n";
        $message .= "Pozdrawiamy,\n";
        $message .= get_bloginfo('name');
        
        wp_mail($email, $subject, $message);
    } else {
        // Błąd płatności
        update_post_meta($rezerwacja_id, 'p24_status', 'failed');
        
        $current_title = get_the_title($rezerwacja_id);
        // Usuń istniejący status z tytułu jeśli istnieje
        $clean_title = preg_replace('/ - (OPŁACONE|ODRZUCONE)$/', '', $current_title);
        
        wp_update_post(array(
            'ID' => $rezerwacja_id,
            'post_title' => $clean_title . ' - ODRZUCONE'
        ));
        
        // Aktualizuj pole płatności
        update_payment_info_display($rezerwacja_id);
        
        $error_message = isset($body['error']) ? $body['error'] : "Nieznany błąd Przelewy24.";
        error_log('P24 Callback: Płatność odrzucona dla rezerwacji ID ' . $rezerwacja_id . '. Błąd: ' . $error_message);
    }

    // Przekierowanie użytkownika
    $redirect_url = home_url('/rezerwacja-wizyty-dziekujemy/?rezerwacja_id=' . $rezerwacja_id);
    wp_redirect($redirect_url);
    exit;
}