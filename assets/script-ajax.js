jQuery(document).ready(function ($) {
  $(".navigation-button").click(function (e) {
    e.preventDefault();

    if ($(this).hasClass("disabled")) {
      return;
    }

    var week = $(this).data("week");
    var calendarId = $(".owocni-calendar").data("calendar-id");
    var calendarSettings = $(".owocni-calendar").data("calendar-settings");

    $.ajax({
      url: ajaxurl, // Używamy teraz globalnej zmiennej ajaxurl
      type: "POST",
      data: {
        action: "owocni_calendar_get_week",
        calendar_id: calendarId,
        calendar_settings: calendarSettings,
        week: week,
      },
      success: function (response) {
        if (response.success) {
          $(".owocni-calendar table").replaceWith(response.data.html);

          if (response.data.is_current_week) {
            $(".navigation-button.prev").addClass("disabled");
          } else {
            $(".navigation-button.prev").removeClass("disabled");
          }

          $(".navigation-button.current").text(response.data.week_text);
        } else {
          console.error("Błąd AJAX:", response.data.message);
        }
      },
      error: function (jqXHR, textStatus, errorThrown) {
        console.error("Błąd AJAX:", textStatus, errorThrown);
        console.log(jqXHR);
      },
    });
  });
});
