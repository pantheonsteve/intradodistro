(function ($) {

  'use strict';

  Drupal.behaviors.drupalContentSyncFlowForm = {
    attach: function (context, settings) {
      $('.tag-pool-selection',context).each(function(){
        var self = $(this);
        var checkboxes = self.find('.form-checkboxes');

        $('<a href="#">Select all</a>')
          .click(function(e){
            self.find('input').prop('checked', true);

            e.preventDefault();
            return false;
          })
          .appendTo(checkboxes);

        $('<span> | </span>')
          .appendTo(checkboxes);

        $('<a href="#">Deselect all</a>')
          .click(function(e){
            self.find('input').prop('checked', false);

            e.preventDefault();
            return false;
          })
          .appendTo(checkboxes);

      });
    }
  }

})(jQuery, drupalSettings);
