;(function($) {
  $(function () {
    $('.gac-form-control[data-if]').on('gac-initialize', function () {
      var ifs = $(this).data('if'), aThis = this;
      var changeFn = function () {
        //check the condition is valid...
        var isValid = function (ifs) {
          var condition = typeof ifs['_condition_'] === 'string' && $.inArray(ifs['_condition_'], ['and', 'or']) ? ifs['_condition_'] : 'and';
          var valid = condition === 'and' ? true : false;
          for (i in ifs) {
            if(i === '_condition_') {
              // We've handled this for the condition variable
            } else if(typeof ifs[i] === 'string') {
              if(condition === 'and') {
                valid = valid && $(aThis).closest('form').find('*[name="'+i+'"]:not([disabled])').val() === ifs[i];
              } else {
                valid = valid || $(aThis).closest('form').find('*[name="'+i+'"]:not([disabled])').val() === ifs[i];
              }
            } else if (Array.isArray(ifs[i])) {
              if(condition === 'and') {
                valid = valid && $.inArray($(aThis).closest('form').find('*[name="'+i+'"]:not([disabled])').val(), ifs[i]) !== -1;
              } else {
                valid = valid || $.inArray($(aThis).closest('form').find('*[name="'+i+'"]:not([disabled])').val(), ifs[i]) !== -1;
              }
            } else {
              if(condition === 'and') {
                valid = valid && isValid(ifs[i]);
              } else {
                valid = valid || isValid(ifs[i]);
              }
            }
          }
          return valid;
        }
        if(isValid(ifs)) {
          // display the field!
          if($(aThis).prop('disabled') !== false) {
            $(aThis).prop('disabled', false).attr('disabled', undefined).trigger('change').closest('li').removeClass('form-condition-hidden');
          }
        } else {
          //hide the field
          if($(aThis).prop('disabled') !== true) {
            $(aThis).prop('disabled', true).attr('disabled', 'disabled').trigger('change').closest('li').addClass('form-condition-hidden');
          }
        }
      }
      var checkFn = function (ifs) {
        for (i in ifs) {
          if(i === '_condition_') {
            //Ignore here
          } else if(typeof ifs[i] === 'string') {
            $(aThis).closest('form').find('*[name="'+i+'"]').on('change', changeFn)
          } else if (Array.isArray(ifs[i])) {
            $(aThis).closest('form').find('*[name="'+i+'"]').on('change', changeFn)
          } else {
            checkFn(ifs[i]);
          }
        }
      }
      checkFn(ifs)
      changeFn();
    }).trigger('gac-initialize');
    $(document).on('click', 'a.gac-form-delete-group', function (e) {
      $(this).closest('li').remove();
      e.preventDefault(true);
      e.stopPropagation();
      return false;
    });
    $('.gac-order-form-fields').each(function () {
      $(this).sortable({handle: '.draggable-handle'});
    });
    $(document).on('click', 'a.gac-form-clone-group', function (e) {
      //Not that easy, we need to update the name!
      alert('This is not implemented like this yet! Save the item, new empty field should be available');
      return;
      var p = $(this).closest('li');
      var cnt = p.clone(true, true);
      cnt.find('input, select, textare').val('');
      p.append(cnt);
      $(this).parent().find('>a').remove();
      e.preventDefault(true);
      e.stopPropagation();
      return false;
    });
  });
}(jQuery));
