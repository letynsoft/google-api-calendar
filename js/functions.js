;(function($) {
  $(function () {
    $('.type-gac-calendar .book, .type-gac-calendar-container .modal .close').on('click', function (e) {
      var modal = $(this).closest('.type-gac-calendar-container').find('.modal').toggleClass('show');
      modal.find('input[type="date"]').val($(this).data('date'))
      modal.find('input[type="time"]').val($(this).data('time'))
      e.preventDefault();
      e.stopPropagation();
      return false;
    });
    $('.booking-form').on('submit', function (e) {
      //Now do the ajax
      jQuery.post(ajaxurl, {
        'p': $(this).data('p'),
        'action': 'gac_book',
        '_wpnonce': $(this).data('wpnonce'),
        'form_data': $(this).serialize(),
        dataType: "json",
      }, function(response) {
        var o = $('#form-errors')
        if(response.success) {
          o.removeClass('has-error').text (response.message);
          window.location.reload();
        } else {
          o.addClass('has-error').text (response.error)
        }
      });
      e.preventDefault(true);
      e.stopPropagation();
      return false;
    });
  });
}(jQuery));
