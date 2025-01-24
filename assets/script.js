jQuery(document).ready(function ($) {
  $(".rezerwuj-termin").click(function () {
    const date = $(this).data("date");
    const time = $(this).data("time");
    const calendar = $(this).data("calendar");
    const endTime = addTime(time, "' . $calendar_settings['interval'] . '");

    $("#rezerwacja_data").val(date);
    $("#rezerwacja_godzina_od").val(time);
    $("#rezerwacja_godzina_do").val(endTime);
    $("#rezerwacja_kalendarz").val(calendar);
    $("#rezerwacja-modal h2").html(
      "Rezerwujesz termin<br>" + date + " " + time
    );

    $("#modal-backdrop").addClass("show");
    $("#rezerwacja-modal").addClass("show");
    $("body").css("overflow", "hidden");
  });

  $(".zamknij-modal").click(function () {
    $("#rezerwacja-modal").removeClass("show");
    $("#modal-backdrop").removeClass("show");
    $("body").css("overflow", "auto");
  });

  $(window).click(function (event) {
    if (event.target.id == "modal-backdrop") {
      $("#rezerwacja-modal").removeClass("show");
      $("#modal-backdrop").removeClass("show");
      $("body").css("overflow", "auto");
    }
  });

  function addTime(startTime, minutes) {
    const [hours, mins] = startTime.split(":").map(Number);
    const totalMinutes = mins + parseInt(minutes, 10);
    const newHours = (hours + Math.floor(totalMinutes / 60)) % 24;
    const newMins = totalMinutes % 60;
    return `${String(newHours).padStart(
      2,
      "0"
    )}:${String(newMins).padStart(2, "0")}`;
  }
  $("#datepicker").datepicker({
    dateFormat: "yy-mm-dd",
    firstDay: 1,
    minDate: 0,
    onSelect: function (dateText) {
      window.location.href = "?week=" + dateText + "#calendar";
    },
  });
});
