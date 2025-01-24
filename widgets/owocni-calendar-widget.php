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

        echo '<div class="owocni-calendar">';
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
            echo '<a href="?week=' . $prev_week . '">&laquo; Poprzedni tydzień</a> | ';
        } else {
            echo '<span class="disabled-link">&laquo; Poprzedni tydzień</span> | ';
        }
    
        echo '<input type="text" id="datepicker" placeholder="Skocz do daty..."> | '; // Usunięty atrybut value
    
        echo '<a href="?week=' . $next_week . '">Następny tydzień &raquo;</a>';
        echo '</div>';
    
        echo '<script type="text/javascript">
            jQuery(document).ready(function($) {
                $("#datepicker").datepicker({
                    dateFormat: "yy-mm-dd",
                    firstDay: 1,
                    onSelect: function(dateText) {
                        window.location.href = "?week=" + dateText;
                    }
                });
            });
        </script>';
    
        echo '<table>';
        echo '<thead><tr>';
        for ($i = 0; $i < 7; $i++) {
            $day_timestamp = strtotime('+' . $i . ' days', $current_week_start);
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
    
                        // Pobieranie rezerwacji dla danego dnia i kalendarza
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
                            'posts_per_page' => -1, // Pobierz wszystkie rezerwacje
                        ));
    
                        $is_reserved = false;
                        foreach ($rezerwacje as $rezerwacja) {
                            $godzina_od = get_post_meta($rezerwacja->ID, 'godzina_od', true);
                            $godzina_do = get_post_meta($rezerwacja->ID, 'godzina_do', true);
    
                            if ($current_time >= $godzina_od && $current_time < $godzina_do) { // Zmiana na < żeby nie blokowało następnej godziny
                                $is_reserved = true;
                                break;
                            }
                        }
    
                        if ($start_is_set && $end_is_set && $calendar_settings['start'][$day_pl] !== '--:--' && $calendar_settings['end'][$day_pl] !== '--:--' && $calendar_settings['start'][$day_pl] !== '' && $calendar_settings['end'][$day_pl] !== '') {
                            if ($current_time >= $calendar_settings['start'][$day_pl] && $current_time <= $calendar_settings['end'][$day_pl] && !$is_reserved) {
                                echo '<button class="rezerwuj-termin" data-date="' . date('Y-m-d', $day_timestamp) . '" data-time="' . $current_time . '" data-calendar="' . $calendar_id . '">'. $current_time .'</button>';
                            } else if ($current_time >= $calendar_settings['start'][$day_pl] && $current_time <= $calendar_settings['end'][$day_pl] && $is_reserved){
                                echo "";
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
        echo '<div id="rezerwacja-modal" style="display:none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 9999; justify-content: center; align-items: center;">
        <div style="background-color: white; padding: 20px; border-radius: 5px; max-width: 600px; position: relative;">
            <span class="zamknij-modal" style="position: absolute; top: 10px; right: 10px; cursor: pointer;">&times;</span>
            <h2>Formularz Rezerwacji</h2>
            <form id="rezerwacja-form" method="post" action="' . admin_url('admin-post.php') . '">
                <input type="hidden" name="action" value="zapisz_rezerwacje">
                <input type="hidden" id="rezerwacja_data" name="data">
                <input type="hidden" id="rezerwacja_godzina_od" name="godzina_od">
                <input type="hidden" id="rezerwacja_godzina_do" name="godzina_do">
                <input type="hidden" name="post_type" value="rezerwacja">
                <input type="hidden" name="wybrany_kalendarz" id="rezerwacja_kalendarz">
                <label for="imie_nazwisko">Imię i nazwisko:</label><br>
                <input type="text" id="imie_nazwisko" name="imie_nazwisko"><br><br>
                <label for="dodatkowe_informacje">Dodatkowe informacje:</label><br>
                <textarea id="dodatkowe_informacje" name="dodatkowe_informacje"></textarea><br><br>
                <label for="email">Email:</label><br>
                <input type="email" id="email" name="email"><br><br>
                <label for="telefon">Telefon:</label><br>
                <input type="text" id="telefon" name="telefon"><br><br>
                <input type="submit" value="Zarezerwuj">
            </form>
        </div>
    </div>';

    echo '<script>
jQuery(document).ready(function($) {
    $(".rezerwuj-termin").click(function() {
        const date = $(this).data("date");
        const time = $(this).data("time");
        const calendar = $(this).data("calendar");
        const endTime = addTime(time, "' . $calendar_settings['interval'] . '");

        $("#rezerwacja_data").val(date);
        $("#rezerwacja_godzina_od").val(time);
        $("#rezerwacja_godzina_do").val(endTime);
        $("#rezerwacja_kalendarz").val(calendar);
        $("#rezerwacja-modal").show();
    });

    $(".zamknij-modal").click(function() {
        $("#rezerwacja-modal").hide();
    });

    function addTime(startTime, minutes) {
        const [hours, mins] = startTime.split(":").map(Number);
        const totalMinutes = mins + parseInt(minutes, 10);
        const newHours = (hours + Math.floor(totalMinutes / 60)) % 24;
        const newMins = totalMinutes % 60;
        return `${String(newHours).padStart(2, "0")}:${String(newMins).padStart(2, "0")}`;
    }
});
</script>';
    }
}