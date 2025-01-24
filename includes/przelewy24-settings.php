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
    register_setting('owocni-calendar-settings-group', 'owocni_calendar_p24_pos_id');
    register_setting('owocni-calendar-settings-group', 'owocni_calendar_p24_crc'); 

    add_settings_section('owocni_calendar_p24_settings_section', 'Ustawienia Przelewy24', null, 'owocni-calendar-settings');

    add_settings_field('owocni_calendar_p24_mode', 'Tryb Przelewy24', 'owocni_calendar_p24_mode_callback', 'owocni-calendar-settings', 'owocni_calendar_p24_settings_section');
    add_settings_field('owocni_calendar_p24_merchant_id', 'ID Sprzedawcy (Merchant ID)', 'owocni_calendar_p24_merchant_id_callback', 'owocni-calendar-settings', 'owocni_calendar_p24_settings_section');
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
        wp_die('Brak ID rezerwacji.');
    }

    $rezerwacja_id = intval($_GET['rezerwacja_id']);

    // 2. Pobieranie ustawień P24
    $merchantId = get_option('owocni_calendar_p24_merchant_id');
    $posId = get_option('owocni_calendar_p24_pos_id');
    $crc = get_option('owocni_calendar_p24_crc');

    // 3. Pobieranie danych z POST (od Przelewy24)
    if (empty($_POST)) {
        wp_die('Brak danych z Przelewy24.');
    }

    $sessionId = get_post_meta($rezerwacja_id, 'p24_session_id', true);
    $orderId = isset($_POST['p24_order_id']) ? $_POST['p24_order_id'] : null;
    $amount = isset($_POST['p24_amount']) ? $_POST['p24_amount'] : null;
    $currency = isset($_POST['p24_currency']) ? $_POST['p24_currency'] : null;

        if (empty($sessionId) || empty($orderId) || empty($amount) || empty($currency)) {
        wp_die('Brak wymaganych danych z Przelewy24 lub w bazie danych.');
    }

    // 4. Obliczanie sumy kontrolnej (sign) – **KLUCZOWE!**
    $sign = hash('sha384', $posId . '|' . $orderId . '|' . $amount . '|' . $currency . '|' . $sessionId . '|' . $crc);

    // 5. Przygotowanie danych do weryfikacji
        $verifyData = array(
                'merchantId' => $merchantId,
        'posId' => $posId,
        'sessionId' => $sessionId,
        'orderId' => $orderId,
        'amount' => $amount,
        'currency' => $currency,
        'sign' => $sign
        );

    // 6. Wysyłanie żądania weryfikującego do API Przelewy24
    $url = 'https://secure.przelewy24.pl/api/v1/transaction/verify';

    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($verifyData),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false, // **TYLKO DO TESTÓW! W PRODUKCJI USUNĄĆ!**
    ));

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        $error_msg = curl_error($ch);
        error_log("Błąd cURL w weryfikacji P24: " . $error_msg); // Logowanie błędu
        wp_die('Błąd weryfikacji płatności.');
    }
    curl_close($ch);

    // 7. Obsługa odpowiedzi z API (parsowanie JSON)
    $response_data = json_decode($response, true);

    if (isset($response_data['status']) && $response_data['status'] == 200) {
        // Płatność OK
        update_post_meta($rezerwacja_id, 'p24_status', 'paid');
                update_post_meta($rezerwacja_id, 'p24_order_id', $orderId);
        wp_update_post(array(
            'ID' => $rezerwacja_id,
            'post_title' => 'Rezerwacja - opłacona'
        ));
    } else {
        // Błąd płatności
        update_post_meta($rezerwacja_id, 'p24_status', 'failed');
        wp_update_post(array(
            'ID' => $rezerwacja_id,
            'post_title' => 'Rezerwacja - odrzucona'
        ));
                $error_message = isset($response_data['error']['message']) ? $response_data['error']['message'] : "Nieznany błąd Przelewy24.";
        error_log("Błąd P24: " . $error_message); // Logowanie błędu
    }

    $redirect_url = 'https://propozycje.owocni.pl/akademiakrakenaKopia/rezerwacja-wizyty-dziekujemy/?rezerwacja_id=' . $rezerwacja_id;
    wp_redirect($redirect_url);
    exit;
}