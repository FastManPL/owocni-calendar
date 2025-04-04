<?php 

if ( ! defined( 'ABSPATH' ) ) exit;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Utils;

class Owocni_Calendar_Widget extends \Elementor\Widget_Base {

    public function get_name() {
        return 'owocni-calendar-widget'; 
    }

    public function get_title() {
        return __( 'Owocni Calendar Widget', 'owocni-calendar' );
    }

    public function get_icon() {
        return 'eicon-calendar'; 
    }

    public function get_categories() {
        return [ 'basic' ];
    }

    protected function _register_controls() {
        $this->start_controls_section(
            'content_section',
            [
                'label' => __( 'Ustawienia kalendarza', 'owocni-calendar' ),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );
        $this->add_control(
            'calendar_id',
            [
                'label' => __( 'Wybierz kalendarz', 'owocni-calendar' ),
                'type' => Controls_Manager::SELECT,
                'options' => $this->get_calendar_options(),
            ]
        );

        $this->end_controls_section();
    }
    private function get_calendar_options() {
        $calendars = get_posts( array(
            'post_type' => 'owocni_calendar',
            'posts_per_page' => -1,
        ) );
        $options = [];
        foreach ( $calendars as $calendar ) {
            $options[ $calendar->ID ] = $calendar->post_title;
        }
        return $options;
    }
    protected function render()
    {
        $settings = $this->get_settings_for_display();
        $calendar_id = $settings['calendar_id'];

        if (empty($calendar_id)) {
            echo '<p>' . __('Wybierz kalendarz w ustawieniach widgetu.', 'owocni-calendar') . '</p>';
            return;
        }

        $calendar_settings = get_post_meta($calendar_id, '_owocni_calendar_settings', true);

        if (empty($calendar_settings)) {
            echo '<p>' . __('Ustawienia kalendarza są puste. Uzupełnij je w panelu administracyjnym.', 'owocni-calendar') . '</p>';
            return;
        }

        echo '<div class="owocni-calendar" id="calendar">';
        $this->render_calendar($calendar_id, $calendar_settings);
        echo '</div>';
    }

    private function render_calendar($calendar_id, $calendar_settings) {
        wp_enqueue_script('jquery-ui-datepicker');
        wp_enqueue_style('jquery-ui-datepicker', 'https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css');
        wp_enqueue_script('jquery');
        $current_date = isset($_GET['week']) ? strtotime($_GET['week']) : time();
        $current_week_start = strtotime('monday this week', $current_date);
        
        if ($current_week_start < strtotime('monday this week')) {
            $current_week_start = strtotime('monday this week');
        }
        $next_week = date('Y-m-d', strtotime('+1 week', $current_week_start));
        echo '<div class="calendar-navigation">';
        if ($current_week_start > strtotime('monday this week')) {
            $prev_week = date('Y-m-d', strtotime('-1 week', $current_week_start));
            echo '<a href="?week=' . $prev_week . '#calendar">&laquo; Poprzedni tydzień</a> ';
        } else {
            echo '<span class="disabled-link">&laquo; Poprzedni tydzień</span> ';
        }
        echo '<input type="text" id="datepicker" placeholder="Skocz do daty..."> '; 
        echo '<a href="?week=' . $next_week . '#calendar">Następny tydzień &raquo;</a>';
        echo '</div>';
        
        echo '<table>';
        echo '<thead><tr>';
        $day_pl = '';
        for ($i = 0; $i < 7; $i++) {
            $day_timestamp = strtotime('+' . $i . ' days', $current_week_start);
            if (isset($calendar_settings['start'][$day_pl]) && $calendar_settings['start'][$day_pl] !== '--:--' && $calendar_settings['start'][$day_pl] !== '') {
                $all_start_times[] = $calendar_settings['start'][$day_pl];
            }
            echo '<th>' . date_i18n('l, j F', $day_timestamp) . '</th>';
        }
        echo '</tr></thead>';
        echo '<tbody>';
        $all_start_times = [];
        $all_end_times = [];
        $latest_time = null;
        for ($i = 0; $i < 7; $i++) {
            $day_timestamp = strtotime('+' . $i . ' days', $current_week_start);
            $day_name = strtolower(date('l', $day_timestamp));
            
            switch ($day_name) {
                case 'monday': $day_pl = 'poniedziałek'; break;
                case 'tuesday': $day_pl = 'wtorek'; break;
                case 'wednesday': $day_pl = 'środa'; break;
                case 'thursday': $day_pl = 'czwartek'; break;
                case 'friday': $day_pl = 'piątek'; break;
                case 'saturday': $day_pl = 'sobota'; break;
                case 'sunday': $day_pl = 'niedziela'; break;
            }
            if (isset($calendar_settings['start'][$day_pl]) && $calendar_settings['start'][$day_pl] !== '--:--' && $calendar_settings['start'][$day_pl] !== '') {
                $all_start_times[] = $calendar_settings['start'][$day_pl];
            }
            if (isset($calendar_settings['end'][$day_pl]) && $calendar_settings['end'][$day_pl] !== '--:--' && $calendar_settings['end'][$day_pl] !== '') {
                $all_end_times[] = $calendar_settings['end'][$day_pl];
            }
            if (isset($calendar_settings['end'][$day_pl]) && $calendar_settings['end'][$day_pl] !== '--:--' && $calendar_settings['end'][$day_pl] !== '') {
                $latest_time = $latest_time === null ? $calendar_settings['end'][$day_pl] : max($latest_time, $calendar_settings['end'][$day_pl]);
            }
        }
        $earliest_time = !empty($all_start_times) ? min($all_start_times) : null;

        $latest_time = !empty($all_end_times) ? max($all_end_times) : null;
        if ($earliest_time !== null && $latest_time !== null) {
            $current_time = $earliest_time;
            do {
                echo '<tr>';
                for ($i = 0; $i < 7; $i++) {
                    $day_timestamp = strtotime('+' . $i . ' days', $current_week_start);
                    $day_name = strtolower(date('l', $day_timestamp));
                    $day_pl = '';
                    switch ($day_name) {
                        case 'monday': $day_pl = 'poniedziałek'; break;
                        case 'tuesday': $day_pl = 'wtorek'; break;
                        case 'wednesday': $day_pl = 'środa'; break;
                        case 'thursday': $day_pl = 'czwartek'; break;
                        case 'friday': $day_pl = 'piątek'; break;
                        case 'saturday': $day_pl = 'sobota'; break;
                        case 'sunday': $day_pl = 'niedziela'; break;
                    }
                    echo '<td';
                    if ($day_timestamp < strtotime('today')) {
                        echo ' class="disabled"';
                    }
                    echo '>';
                    if ($day_timestamp >= strtotime('today')) {
                        $start_is_set = isset($calendar_settings['start'][$day_pl]);
                        $end_is_set = isset($calendar_settings['end'][$day_pl]);
                        $rezerwacje = get_posts(array(
                            'post_type' => 'rezerwacja',
                            'meta_query' => array(
                                array(
                                    'key' => 'data',
                                    'value' => date('Y-m-d', $day_timestamp),
                                    'compare' => '=',
                                ),
                                array(
                                    'key' => 'wybrany_kalendarz',
                                    'value' => $calendar_id,
                                    'compare' => '=',
                                ),
                            ),
                            'posts_per_page' => -1, 
                        ));
                        $is_reserved = false;
                        foreach ($rezerwacje as $rezerwacja) {
                            if (strpos($rezerwacja->post_title, 'ODRZUCONE') !== false && (current_time('timestamp') - get_post_time('U', true, $rezerwacja->ID)) > 2 * HOUR_IN_SECONDS) {
                                continue;
                            }
                            $godzina_od = get_post_meta($rezerwacja->ID, 'godzina_od', true);
                            $godzina_do = get_post_meta($rezerwacja->ID, 'godzina_do', true);
                            if ($current_time >= $godzina_od && $current_time < $godzina_do) {
                                $is_reserved = true;
                                break;
                            }
                        }
                        $offset_total_minutes = 0;
                        if ($day_timestamp == strtotime('today')) {
                            $offset = $calendar_settings['offset'];
                            $offset_parts = explode(':', $offset);
                            $offset_hours = intval($offset_parts[0]);
                            $offset_minutes = intval($offset_parts[1]);
                            $offset_total_minutes = ($offset_hours * 60) + $offset_minutes;

                            $current_date_time2 = current_time('Y-m-d H:i');
                            $slot_date_time2 = date('Y-m-d', $day_timestamp) . ' ' . $current_time;

                            if (strtotime($slot_date_time2) <= strtotime($current_date_time2 . " +" . $offset_total_minutes . " minutes")) {
                                $current_time_ts2 = strtotime($current_time);
                                if ($current_time_ts2 !== false) {
                                    $next_time_ts2 = $current_time_ts2 + ($calendar_settings['interval'] * 60);
                                    $current_time2 = date('H:i', $next_time_ts2);
                                    continue;
                                } else {
                                    break;
                                }
                            }
                        } 
                        if ($start_is_set && $end_is_set && $calendar_settings['start'][$day_pl] !== '--:--' && $calendar_settings['end'][$day_pl] !== '--:--' && $calendar_settings['start'][$day_pl] !== '' && $calendar_settings['end'][$day_pl] !== '') {
                            if ($current_time >= $calendar_settings['start'][$day_pl] && $current_time <= $calendar_settings['end'][$day_pl] && !$is_reserved) {
                                echo '<button class="rezerwuj-termin" data-date="' . date('Y-m-d', $day_timestamp) . '" data-time="' . $current_time . '" data-calendar="' . $calendar_id . '" data-interval="' . $calendar_settings['interval'] . '" >'. $current_time .'</button>';
                            } else if ($current_time >= $calendar_settings['start'][$day_pl] && $current_time <= $calendar_settings['end'][$day_pl] && $is_reserved){
                                echo '';
                            } 
                        } else if ($day_timestamp >= strtotime('today') && (!isset($calendar_settings['start'][$day_pl]) || $calendar_settings['start'][$day_pl] === '--:--' || $calendar_settings['start'][$day_pl] === '')) {
                            echo "";
                        } else if ($day_timestamp >= strtotime('today') && (!isset($calendar_settings['start'][$day_pl]) || $calendar_settings['start'][$day_pl] !== '--:--' || $calendar_settings['start'][$day_pl] !== '')){
                            echo "Brak dostępnych terminów";
                        }
                    }
                    echo '</td>';
                }
                echo '</tr>';
                $current_time_ts = strtotime($current_time);
                if ($current_time_ts !== false) {
                    $next_time_ts = $current_time_ts + ($calendar_settings['interval'] * 60);
                    $current_time = date('H:i', $next_time_ts);
                    if($current_time == "00:00" && date('H:i', strtotime($latest_time)) != "00:00"){
                        break;
                    }
                } else {
                    break;
                }
            } while ($current_time <= $latest_time);
        } else {
            echo '<tr><td colspan="7">Brak ustawionych godzin otwarcia.</td></tr>';
        }
        echo '</tbody>';
        echo '</table>';
        echo '<div id="modal-backdrop"></div>';
        echo '<div id="rezerwacja-modal">
            <div class="modal-content">
                <span class="zamknij-modal">&times;</span>
                <h2>Formularz Rezerwacji</h2>';

        // Sprawdzenie czy jest to powrót z płatności
        if (isset($_GET['p24_order_id']) && isset($_GET['p24_session_id'])) {
            $p24_order_id = sanitize_text_field($_GET['p24_order_id']);
            $p24_session_id = sanitize_text_field($_GET['p24_session_id']);
            
            // Znajdź rezerwację po session_id
            $rezerwacje = get_posts(array(
                'post_type' => 'rezerwacja',
                'meta_query' => array(
                    array(
                        'key' => 'p24_session_id',
                        'value' => $p24_session_id,
                        'compare' => '=',
                    ),
                ),
                'posts_per_page' => 1,
            ));
            
            if (!empty($rezerwacje)) {
                $rezerwacja_id = $rezerwacje[0]->ID;
                
                // Aktualizuj dane płatności
                update_post_meta($rezerwacja_id, 'p24_order_id', $p24_order_id);
                
                // Sprawdź status płatności w P24
                $merchantId = get_option('owocni_calendar_p24_merchant_id');
                $posId = get_option('owocni_calendar_p24_pos_id');
                $crc = get_option('owocni_calendar_p24_crc');
                $apiKey = get_option('owocni_calendar_p24_api_key');
                $p24Mode = get_option('owocni_calendar_p24_mode', 'sandbox');
                $isSandbox = ($p24Mode === 'sandbox');
                
                $apiUrl = $isSandbox ? 'https://sandbox.przelewy24.pl/api/v1/transaction/verify' : 'https://secure.przelewy24.pl/api/v1/transaction/verify';
                
                $data = array(
                    'merchantId' => $merchantId,
                    'posId' => $posId,
                    'sessionId' => $p24_session_id,
                    'amount' => get_post_meta($rezerwacja_id, 'kwota', true),
                    'currency' => 'PLN',
                    'orderId' => $p24_order_id
                );
                
                $sign = hash('sha384', json_encode([
                    'sessionId' => $session_id,
                    'merchantId' => (int)$merchantId,
                    'amount' => (int)$kwota,
                    'currency' => 'PLN',
                    'crc' => $crc
                ]));
                $data['sign'] = $sign;
                
                // Wywołaj API P24 do weryfikacji transakcji
                $response = wp_remote_post($apiUrl, array(
                    'headers' => array(
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Basic ' . base64_encode($merchantId . ':' . $apiKey)
                    ),
                    'body' => json_encode($data)
                ));
                
                if (!is_wp_error($response)) {
                    $body = json_decode(wp_remote_retrieve_body($response), true);
                    
                    if (isset($body['data']['status']) && $body['data']['status'] === 'success') {
                        // Płatność zakończona sukcesem
                        update_post_meta($rezerwacja_id, 'p24_status', 'completed');
                        echo '<div class="payment-success">
                                <h3>Płatność zakończona pomyślnie!</h3>
                                <p>Twoja rezerwacja została potwierdzona. Dziękujemy!</p>
                            </div>';
                            
                        // Wyślij e-mail z potwierdzeniem
                        $email = get_post_meta($rezerwacja_id, 'email', true);
                        $imie_nazwisko = get_post_meta($rezerwacja_id, 'imie_nazwisko', true);
                        $data = get_post_meta($rezerwacja_id, 'data', true);
                        $godzina_od = get_post_meta($rezerwacja_id, 'godzina_od', true);
                        $godzina_do = get_post_meta($rezerwacja_id, 'godzina_do', true);
                        
                        $subject = 'Potwierdzenie rezerwacji';
                        $message = "Witaj $imie_nazwisko,\n\n";
                        $message .= "Twoja rezerwacja na dzień $data w godzinach $godzina_od - $godzina_do została potwierdzona.\n";
                        $message .= "Dziękujemy za dokonanie rezerwacji i płatności!\n\n";
                        $message .= "Pozdrawiamy,\n";
                        $message .= get_bloginfo('name');
                        
                        wp_mail($email, $subject, $message);
                    } else {
                        // Płatność nie powiodła się
                        update_post_meta($rezerwacja_id, 'p24_status', 'failed');
                        echo '<div class="payment-error">
                                <h3>Płatność nie została zrealizowana</h3>
                                <p>Wystąpił problem z płatnością. Prosimy spróbować ponownie lub skontaktować się z nami.</p>
                            </div>';
                    }
                } else {
                    // Błąd komunikacji z API
                    update_post_meta($rezerwacja_id, 'p24_status', 'error');
                    echo '<div class="payment-error">
                            <h3>Błąd weryfikacji płatności</h3>
                            <p>Wystąpił problem z weryfikacją płatności. Prosimy skontaktować się z nami.</p>
                        </div>';
                }
            } else {
                echo '<div class="payment-error">
                        <h3>Nie znaleziono rezerwacji</h3>
                        <p>Nie znaleziono rezerwacji powiązanej z tą płatnością. Prosimy skontaktować się z nami.</p>
                    </div>';
            }
        } else if (isset($_POST['action']) && $_POST['action'] == 'zapisz_rezerwacje') {
            // Pobierz dane konfiguracyjne Przelewy24
            $merchantId = get_option('owocni_calendar_p24_merchant_id');
            $posId = get_option('owocni_calendar_p24_pos_id');
            $crc = get_option('owocni_calendar_p24_crc');
            $apiKey = get_option('owocni_calendar_p24_api_key');
            $p24Mode = get_option('owocni_calendar_p24_mode', 'sandbox');
            $isSandbox = ($p24Mode === 'sandbox');
            
            if (!$merchantId || !$posId || !$crc || !$apiKey) {
                error_log("Błąd: Brak ustawień Przelewy24.");
                echo '<p style="color: red;">Błąd: Brak ustawień Przelewy24.</p>';
                return;
            }
            
            // Pobierz dane formularza
            $data = isset($_POST['data']) ? sanitize_text_field($_POST['data']) : null;
            $godzina_od = isset($_POST['godzina_od']) ? sanitize_text_field($_POST['godzina_od']) : null;
            $imie_nazwisko = isset($_POST['imie_nazwisko']) ? sanitize_text_field($_POST['imie_nazwisko']) : null;
            $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : null;
            $telefon = isset($_POST['telefon']) ? sanitize_text_field($_POST['telefon']) : null;
            $dodatkowe_informacje = isset($_POST['dodatkowe_informacje']) ? sanitize_textarea_field($_POST['dodatkowe_informacje']) : null;
            $wybrany_kalendarz = isset($_POST['wybrany_kalendarz']) ? intval($_POST['wybrany_kalendarz']) : null;
        
            if (!$data || !$godzina_od || !$wybrany_kalendarz) {
                error_log("Brakujące dane w formularzu.");
                echo '<p style="color: red;">Błąd: Brakujące dane w formularzu.</p>';
                return;
            }
        
            $interval = isset($calendar_settings['interval']) ? intval($calendar_settings['interval']) : 30;
            $godzina_do = date('H:i', strtotime($godzina_od . ' +' . $interval . ' minutes'));
            if ($godzina_do === false) {
                error_log("Błąd: Niepoprawny format godziny od: " . $godzina_od);
                echo '<p style="color: red;">Błąd: Niepoprawny format godziny od.</p>';
                return;
            }
            
            // Utwórz unikalny identyfikator sesji
            $session_id = uniqid();
            
            // Pobierz cenę z ustawień kalendarza lub ustaw domyślną
            $cena_wizyty = get_post_meta($wybrany_kalendarz, '_owocni_calendar_cena', true);
            if (empty($cena_wizyty)) {
                $cena_wizyty = 100; // Domyślna wartość 100 PLN
            }
            
            // Kwota w groszach (format wymagany przez P24)
            $kwota = intval($cena_wizyty * 100);
            
            // Zapisz rezerwację
            $rezerwacja_id = wp_insert_post(array(
                'post_type' => 'rezerwacja',
                'post_title' => $data . ' ' . $godzina_od . ' - ' . $godzina_do . ' - ' . $imie_nazwisko,
                'post_status' => 'publish',
                'meta_input' => array(
                    'data' => $data,
                    'godzina_od' => $godzina_od,
                    'godzina_do' => $godzina_do,
                    'imie_nazwisko' => $imie_nazwisko,
                    'email' => $email,
                    'telefon' => $telefon,
                    'dodatkowe_informacje' => $dodatkowe_informacje,
                    'wybrany_kalendarz' => $wybrany_kalendarz,
                    'p24_session_id' => $session_id,
                    'p24_order_id' => null,
                    'p24_status' => 'pending',
                    'kwota' => $kwota,
                ),
            ));
        
            if (is_wp_error($rezerwacja_id)) {
                error_log("Błąd zapisu rezerwacji: " . $rezerwacja_id->get_error_message());
                echo '<p style="color: red;">Błąd zapisu rezerwacji.</p>';
                return; 
            }
            
            // Ustaw adres powrotu po płatności
            $returnUrl = add_query_arg(array(
                'p24_session_id' => $session_id,
            ), get_permalink());
            
            // Przygotuj dane dla Przelewy24
            $p24ApiUrl = $isSandbox ? 'https://sandbox.przelewy24.pl/api/v1/transaction/register' : 'https://secure.przelewy24.pl/api/v1/transaction/register';
            $p24RedirectUrl = $isSandbox ? 'https://sandbox.przelewy24.pl/trnRequest/' : 'https://secure.przelewy24.pl/trnRequest/';
            
            // Przygotuj dane dla transakcji
            $description = 'Rezerwacja: ' . $data . ' ' . $godzina_od . ' - ' . $godzina_do;
            $urlStatus = admin_url('admin-post.php?action=p24_status_update&rezerwacja_id=' . $rezerwacja_id);
            $urlReturn = add_query_arg(array(
                'p24_session_id' => $session_id,
                'rezerwacja_id' => $rezerwacja_id
            ), get_permalink());
            
            // Przygotuj sumę kontrolną - używamy klucza CRC do podpisu
            $sign = hash('sha384', json_encode([
                'sessionId' => $session_id,
                'merchantId' => (int)$merchantId,
                'amount' => (int)$kwota,
                'currency' => 'PLN',
                'crc' => $crc
            ]));
            
            $p24Data = array(
                'merchantId' => intval($merchantId),
                'posId' => intval($posId),
                'sessionId' => $session_id,
                'amount' => $kwota,
                'currency' => 'PLN',
                'description' => $description,
                'email' => $email,
                'client' => $imie_nazwisko,
                'country' => 'PL',
                'language' => 'pl',
                'urlReturn' => $urlReturn,
                'urlStatus' => $urlStatus,
                'sign' => $sign
            );
            
            // Dodaj debugowanie
            error_log('P24 Request Data: ' . print_r($p24Data, true));
            
            // Wywołaj API P24 do rejestracji transakcji - używamy klucza API do autoryzacji
            $response = wp_remote_post($p24ApiUrl, array(
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Basic ' . base64_encode($merchantId . ':' . $apiKey)
                ),
                'body' => json_encode($p24Data),
                'timeout' => 45,
                'sslverify' => $isSandbox ? false : true
            ));
            
            if (is_wp_error($response)) {
                error_log('P24 API Error: ' . $response->get_error_message());
                echo '<p style="color: red;">Błąd podczas łączenia z systemem płatności: ' . esc_html($response->get_error_message()) . '</p>';
            } else {
                $status_code = wp_remote_retrieve_response_code($response);
                $body = json_decode(wp_remote_retrieve_body($response), true);
                
                error_log('P24 API Response: ' . print_r($body, true));
                
                if ($status_code === 200 && isset($body['data']['token'])) {
                    $token = $body['data']['token'];
                    
                    // Zapisz token w metadanych rezerwacji
                    update_post_meta($rezerwacja_id, 'p24_token', $token);
                    
                    // Przekieruj do strony płatności Przelewy24
                    echo '<script>window.location.href = "' . $p24RedirectUrl . $token . '";</script>';
                    echo '<p>Przekierowujemy do systemu płatności Przelewy24...</p>';
                    echo '<p>Jeśli nie zostaniesz przekierowany automatycznie, <a href="' . $p24RedirectUrl . $token . '">kliknij tutaj</a>.</p>';
                } else {
                    // Błąd rejestracji transakcji
                    $error_message = isset($body['error']) ? $body['error'] : 'Nieznany błąd';
                    error_log('P24 Transaction Registration Error: ' . $error_message);
                    echo '<p style="color: red;">Błąd podczas rejestracji transakcji: ' . esc_html($error_message) . '</p>';
                }
            }
        } else { 
            echo '<form id="rezerwacja-form" method="post" action="">
                <input type="hidden" name="action" value="zapisz_rezerwacje">
                <input type="hidden" id="rezerwacja_data" name="data">
                <input type="hidden" id="rezerwacja_godzina_od" name="godzina_od">
                <input type="hidden" id="rezerwacja_godzina_do" name="godzina_do">
                <input type="hidden" name="post_type" value="rezerwacja">
                <input type="hidden" name="wybrany_kalendarz" id="rezerwacja_kalendarz">
                <label for="imie_nazwisko">Imię i nazwisko:</label><br>
                <input type="text" id="imie_nazwisko" value="test debug" name="imie_nazwisko"><br><br>
                <label for="email">Email:</label><br>
                <input type="email" id="email" value="debug@debug.pl" name="email"><br><br>
                <label for="telefon">Telefon:</label><br>
                <input type="text" id="telefon" value="test debug" name="telefon"><br><br>
                <label for="dodatkowe_informacje">Dodatkowe informacje:</label><br>
                <textarea id="dodatkowe_informacje" name="dodatkowe_informacje"></textarea><br><br>
                <input type="submit" value="Zarezerwuj i przejdź do płatności">
            </form>';
        }
        echo '</div></div>';

        // Nie dodajemy skryptu JavaScript, bo używamy external JS w script.js
    }
}

/**
 * Funkcja aktualizująca status płatności P24
 */
function owocni_calendar_p24_status_update() {
    // Obsługa powiadomienia z Przelewy24
    if (isset($_GET['rezerwacja_id'])) {
        $rezerwacja_id = intval($_GET['rezerwacja_id']);
        
        // Pobierz dane konfiguracyjne
        $merchantId = get_option('owocni_calendar_p24_merchant_id');
        $posId = get_option('owocni_calendar_p24_pos_id');
        $crc = get_option('owocni_calendar_p24_crc');
        $apiKey = get_option('owocni_calendar_p24_api_key');
        
        // Pobierz dane z POST
        $p24_merchant_id = isset($_POST['merchantId']) ? sanitize_text_field($_POST['merchantId']) : '';
        $p24_pos_id = isset($_POST['posId']) ? sanitize_text_field($_POST['posId']) : '';
        $p24_session_id = isset($_POST['sessionId']) ? sanitize_text_field($_POST['sessionId']) : '';
        $p24_amount = isset($_POST['amount']) ? sanitize_text_field($_POST['amount']) : '';
        $p24_currency = isset($_POST['currency']) ? sanitize_text_field($_POST['currency']) : '';
        $p24_order_id = isset($_POST['orderId']) ? sanitize_text_field($_POST['orderId']) : '';
        
        if (empty($p24_session_id) || empty($p24_order_id)) {
            wp_die('Brak niezbędnych danych.');
        }
        
        // Weryfikuj dane
        $session_id_from_db = get_post_meta($rezerwacja_id, 'p24_session_id', true);
        
        if ($session_id_from_db !== $p24_session_id) {
            wp_die('Błąd weryfikacji sessionId.');
        }
        
        // Oblicz sumę kontrolną
        $sign = md5($p24_session_id . '|' . $merchantId . '|' . $p24_amount . '|' . $p24_currency . '|' . $crc);
        
        // Przygotuj dane do weryfikacji transakcji
        $p24Mode = get_option('owocni_calendar_p24_mode', 'sandbox');
        $isSandbox = ($p24Mode === 'sandbox');
        $apiUrl = $isSandbox ? 'https://sandbox.przelewy24.pl/api/v1/transaction/verify' : 'https://secure.przelewy24.pl/api/v1/transaction/verify';
        
        $verify_data = array(
            'merchantId' => intval($merchantId),
            'posId' => intval($posId),
            'sessionId' => $p24_session_id,
            'amount' => $p24_amount,
            'currency' => $p24_currency,
            'orderId' => $p24_order_id,
            'sign' => $sign
        );
        
        // Wywołaj API do weryfikacji transakcji
        $response = wp_remote_post($apiUrl, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode($merchantId . ':' . $apiKey)
            ),
            'body' => json_encode($verify_data),
            'timeout' => 45,
            'sslverify' => $isSandbox ? false : true
        ));
        
        if (is_wp_error($response)) {
            error_log('P24 Verify Error: ' . $response->get_error_message());
            wp_die('Błąd weryfikacji płatności.');
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['data']['status']) && $body['data']['status'] === 'success') {
            // Płatność zakończona sukcesem
            update_post_meta($rezerwacja_id, 'p24_status', 'completed');
            update_post_meta($rezerwacja_id, 'p24_order_id', $p24_order_id);
            
            // Aktualizuj tytuł rezerwacji
            wp_update_post(array(
                'ID' => $rezerwacja_id,
                'post_title' => get_the_title($rezerwacja_id) . ' - OPŁACONE'
            ));
            
            // Wyślij e-mail z potwierdzeniem
            $email = get_post_meta($rezerwacja_id, 'email', true);
            $imie_nazwisko = get_post_meta($rezerwacja_id, 'imie_nazwisko', true);
            $data = get_post_meta($rezerwacja_id, 'data', true);
            $godzina_od = get_post_meta($rezerwacja_id, 'godzina_od', true);
            $godzina_do = get_post_meta($rezerwacja_id, 'godzina_do', true);
            
            $subject = 'Potwierdzenie rezerwacji';
            $message = "Witaj $imie_nazwisko,\n\n";
            $message .= "Twoja rezerwacja na dzień $data w godzinach $godzina_od - $godzina_do została potwierdzona.\n";
            $message .= "Dziękujemy za dokonanie rezerwacji i płatności!\n\n";
            $message .= "Pozdrawiamy,\n";
            $message .= get_bloginfo('name');
            
            wp_mail($email, $subject, $message);
            
            echo 'OK';
        } else {
            // Płatność nieudana
            update_post_meta($rezerwacja_id, 'p24_status', 'failed');
            
            // Aktualizuj tytuł rezerwacji
            wp_update_post(array(
                'ID' => $rezerwacja_id,
                'post_title' => get_the_title($rezerwacja_id) . ' - ODRZUCONE'
            ));
            
            error_log('P24 Payment Failed: ' . (isset($body['error']) ? $body['error'] : 'Unknown error'));
            echo 'Payment failed';
        }
        
        exit;
    }
}
add_action('admin_post_p24_status_update', 'owocni_calendar_p24_status_update');
add_action('admin_post_nopriv_p24_status_update', 'owocni_calendar_p24_status_update');
