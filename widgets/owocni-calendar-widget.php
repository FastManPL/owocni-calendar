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
    
        // Zmiana: Użycie date_i18n() do wyświetlania nazwy miesiąca po polsku
        echo '<span class="current-week-text">' . date_i18n('F Y', $current_week_start) . '</span> | ';
        echo '<a href="?week=' . $next_week . '">Następny tydzień &raquo;</a>';
        echo '</div>';
    
        echo '<table>';
    echo '<thead><tr>';
    for ($i = 0; $i < 7; $i++) {
        $day_timestamp = strtotime('+' . $i . ' days', $current_week_start);
        echo '<th>' . date_i18n('l, j F', $day_timestamp) . '</th>';
    }
    echo '</tr></thead>';
    echo '<tbody><tr>';

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

        $times = [];

        // DEBUG: Wyświetlanie ustawień dla danego dnia (do testów)
        // echo "<pre>";
        // var_dump($calendar_settings);
        // echo "</pre>";
        // echo "Dzień: " . $day_pl . "<br>";
        // if (isset($calendar_settings['start'][$day_pl])){
        //     echo "Start: " . $calendar_settings['start'][$day_pl] . "<br>";
        // }
        // if (isset($calendar_settings['end'][$day_pl])){
        //     echo "Koniec: " . $calendar_settings['end'][$day_pl] . "<br>";
        // }

        // KLUCZOWY WARUNEK - ROZŁOŻONY DLA CZYTELNOŚCI
        $start_is_set = isset($calendar_settings['start'][$day_pl]);
        $end_is_set = isset($calendar_settings['end'][$day_pl]);
        $start_is_not_empty = $start_is_set && $calendar_settings['start'][$day_pl] !== '--:--';
        $end_is_not_empty = $end_is_set && $calendar_settings['end'][$day_pl] !== '--:--';

        if (is_array($calendar_settings) && $start_is_set && $end_is_set && $start_is_not_empty && $end_is_not_empty) {
            $start_time = strtotime($calendar_settings['start'][$day_pl]);
            $end_time = strtotime($calendar_settings['end'][$day_pl]);
            $interval = $calendar_settings['interval'] * 60;

            if ($end_time > $start_time) {
                for ($time = $start_time; $time <= $end_time; $time += $interval) {
                    $times[] = date('H:i', $time);
                }
            }
        }

        // WARUNEK WYŚWIETLANIA - ROZŁOŻONY DLA CZYTELNOŚCI
        $is_future_day = $day_timestamp >= strtotime('today');
        $no_times_generated = empty($times);
        $start_is_empty_or_unset = !isset($calendar_settings['start'][$day_pl]) || $calendar_settings['start'][$day_pl] === '--:--';

        if ($is_future_day && $no_times_generated && !$start_is_empty_or_unset) {
            echo "Brak dostępnych terminów";
        } elseif ($is_future_day && $start_is_empty_or_unset) {
            echo ""; // Nic nie wyświetlamy, gdy zakres jest pusty
        } elseif (!empty($times)) {
            echo implode('<br>', $times); // Wyświetlamy terminy
        }

        echo '</td>';
    }

    echo '</tr></tbody>';
    echo '</table>';
}
}