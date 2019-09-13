(function ($) {

  'use strict';

  function showFieldGroups(checkbox,show) {
    var fieldgroups = checkbox.parent().siblings().filter( function() {
      return /field-group/.test($(this).attr("class"));
    });
    if(show) {
      fieldgroups.show();
    }
    else {
      fieldgroups.hide();
    }
  }

  Drupal.behaviors.entityForm = {
    attach: function (context, settings) {
      $('.cms-content-sync-edit-override',context).each(function() {
        var checkbox = $(this);
        showFieldGroups(checkbox,checkbox.is(':checked'));
      });

      $('.cms-content-sync-edit-override-disabled',context).each(function(){
        var element = $(this);
        if(!element.is(':disabled')) {
          element = element.find(':disabled');
        }
        element
          .not(':button')
          .removeAttr('disabled')
          .attr('readonly','readonly');
      });

      $('.cms-content-sync-edit-override',context).click( function(e) {
        var checkbox  = $(this);
        var id        = checkbox.attr('data-cms-content-sync-edit-override-id');
        var override  = checkbox.is(':checked');
        var elements  = $('.cms-content-sync-edit-override-id-'+id);
        showFieldGroups(checkbox,override);
        if(override) {
          elements.removeClass('cms-content-sync-edit-override-hide');
        }
        else {
          elements.addClass('cms-content-sync-edit-override-hide');
        }
      } );

      $(context)
        .find('#ajax-pool-selector-wrapper')
        .once('content-sync-pool-search')
        .each( function() {
          function update(e) {
            var text = input.val();
            parent.find('label').each(function() {
              var label = $(this);
              var checkbox = label.siblings('#' + label.attr("for"));

              if(!text || label.text().toLowerCase().indexOf(text)>=0) {
                label.show();
                checkbox.show();
              }
              else {
                label.hide();
                checkbox.hide();
              }
            });
          }



          var container = $(this);
          var parent = container.find('.form-checkboxes');

          var labels = parent.find('label');

          // Don't show for select boxes
          // Don't show for less than 10 checkboxes / radios
          if(labels.length<10) {
            return;
          }

          var input = $('<input type="text" placeholder="search..." />')
            .keyup(update)
            .keypress(update)
            .change(update)
            .prependTo(parent);
        } );
    }
  };

})(jQuery, drupalSettings);
