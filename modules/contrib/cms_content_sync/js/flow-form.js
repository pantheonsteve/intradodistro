(function ($) {

  'use strict';

  function isArrayEqual(a,b) {
    let i;

    for(i=0;i<a.length;i++) {
      if(b.indexOf(a[i])<0) {
        return false;
      }
    }

    for(i=0;i<b.length;i++) {
      if(a.indexOf(b[i])<0) {
        return false;
      }
    }

    return true;
  }

  function getName(name) {
    if(name.substr(0,13)!='sync_entities') {
      return '';
    }
    return name.substr(14).split(']')[0];
  }

  function getPoolName(name) {
    if(name.substr(0,13)!='sync_entities') {
      return '';
    }

    return name.substr(14).split(']')[2].split('[')[1];
  }

  function isEntityType(name) {
    return getName(name).split('-').length==2;
  }

  function isField(name) {
    return getName(name).split('-').length==3;
  }

  /**
   *
   * @param settings
   * @param settings.setting
   * @param settings.fields
   * @param settings.children
   * @param settings.once
   * @param clb
   */
  function forEachSetting(settings,clb) {
    var setting = settings.setting;
    var fields = settings.fields;
    var children = settings.children;
    var once = settings.once;

    var selector = "[name^='sync_entities[']";
    if(fields && typeof(fields)=='string') {
      selector = "[name^='sync_entities["+fields+"-']";
    }

    if(children) {
      selector += "[name*='["+setting+"][']";
    }
    else {
      selector += "[name$='["+setting+"]']";
    }

    var elements = $(selector);
    if(once) {
      elements = elements.once(once);
    }

    elements.each(function() {
      var self = $(this);
      var name = self.attr("name");

      if(fields ? isField(name) : isEntityType(name)) {
        clb(self);
      }
    });
  }

  function applyFlowTypeSetting() {
    var value = $("input[name='type']:checked").val();
    var has_export = value!='import';
    var has_import = value!='export';

    if(!has_export) {
      forEachSetting({setting:'export'},function(self) {
        self.val('disabled');
      });
    }
    if(!has_import) {
      forEachSetting({setting:'import'},function(self) {
        self.val('disabled');
      });
    }

    if(has_export) {
      $('#sync-entities-table table th:nth-child(4), #sync-entities-table table th:nth-child(5), #sync-entities-table table th:nth-child(6), #sync-entities-table table th:nth-child(7), #sync-entities-table table th:nth-child(12), #sync-entities-table table td:nth-child(4), #sync-entities-table table td:nth-child(5), #sync-entities-table table td:nth-child(6), #sync-entities-table table td:nth-child(7), #sync-entities-table table td:nth-child(12)')
        .show();
    }
    else {
      $('#sync-entities-table table th:nth-child(4), #sync-entities-table table th:nth-child(5), #sync-entities-table table th:nth-child(6), #sync-entities-table table th:nth-child(7), #sync-entities-table table th:nth-child(12), #sync-entities-table table td:nth-child(4), #sync-entities-table table td:nth-child(5), #sync-entities-table table td:nth-child(6), #sync-entities-table table td:nth-child(7), #sync-entities-table table td:nth-child(12)')
        .hide();
    }

    if(has_import) {
      $('#sync-entities-table table th:nth-child(8), #sync-entities-table table th:nth-child(9), #sync-entities-table table th:nth-child(10), #sync-entities-table table th:nth-child(11), #sync-entities-table table td:nth-child(8), #sync-entities-table table td:nth-child(9), #sync-entities-table table td:nth-child(10), #sync-entities-table table td:nth-child(11)')
        .show();
    }
    else {
      $('#sync-entities-table table th:nth-child(8), #sync-entities-table table th:nth-child(9), #sync-entities-table table th:nth-child(10), #sync-entities-table table th:nth-child(11), #sync-entities-table table td:nth-child(8), #sync-entities-table table td:nth-child(9), #sync-entities-table table td:nth-child(10), #sync-entities-table table td:nth-child(11)')
        .hide();
    }
  }

  // Group all pools by default
  var pools = [];
  var onPoolAdded = [];
  var onPoolRemoved = [];

  var ONCE_ID = 'flow-form-type-selection';

  Drupal.behaviors.drupalContentSyncFlowForm = {
    attach: function (context, settings) {
      context = $(context);

      // Initialize general form once.
      context
        .find('.fieldgroup.flow-type-selection')
        .once(ONCE_ID)
        .each(function() {
          console.log('Initializing Flow form javascript.');

          function updateTypeValue(has_export,has_import) {
            // At least one must be set.
            if(!has_export && !has_import) {
              has_export = true;
            }

            var value = has_import ? (has_export ? 'both' : 'import') : 'export';
            $("input[name='type']").prop('checked', false);
            $("input[name='type'][value='"+value+"']").prop('checked', true);
          }

          // Set type to Export, Import, Both
          var has_export = false;
          var has_import = false;
          forEachSetting({setting:'export'},function(self) {
            if(self.val()!='disabled') has_export=true;
          });
          forEachSetting({setting:'import'},function(self) {
            if(self.val()!='disabled') has_import=true;
          });

          updateTypeValue(has_export,has_import);

          $("input[name='type']").change(function() {
            applyFlowTypeSetting();
          });

          forEachSetting({setting:'export_pools',children:true}, function(pool) {
            var name = getPoolName(pool.attr("name"));
            var enabled = pool.parents('tr').find("[name$='[export]']").val()!=='disabled';
            if(enabled && pool.val()!="forbid" && pools.indexOf(name)<0) {
              pools.push(name);
            }
          });

          forEachSetting({setting:'import_pools',children:true}, function(pool) {
            var name = getPoolName(pool.attr("name"));
            var enabled = pool.parents('tr').find("[name$='[import]']").val()!=='disabled';
            if(enabled && pool.val()!="forbid" && pools.indexOf(name)<0) {
              pools.push(name);
            }
          });

          for(var i=0; i<pools.length; i++) {
            $("input[name='pools["+pools[i]+"]']").prop('checked',true);
          }

          $("input[name^='pools[']").each(function() {
            var self = $(this);
            var pool = self.attr("name").substr(6).split(']')[0];

            self.change(function() {
              var i;

              if(self.is(':checked')) {
                pools.push(pool);

                for(i=0; i<onPoolAdded.length; i++) {
                  onPoolAdded[i](pool);
                }
              }
              else {
                pools.splice(pools.indexOf(pool),1);

                for(i=0; i<onPoolRemoved.length; i++) {
                  onPoolRemoved[i](pool);
                }
              }
            });
          });
        });

      // Hide field config by default
      forEachSetting({setting:'handler',once:ONCE_ID+'-hide-fields'},function(entityTypeHandler) {
        if(entityTypeHandler.val()=='ignore') {
          return;
        }

        var name = getName(entityTypeHandler.attr("name"));

        forEachSetting({setting:'handler',fields:name},function(fieldHandler) {
          var fieldRow = fieldHandler.parents('tr');
          if(!fieldHandler.is(':disabled')) {
            if(fieldHandler.val()=='ignore') {
              return;
            }
            if(fieldRow.find("[name$='[export]']").val()=='disabled') {
              return;
            }
            if(fieldRow.find("[name$='[import]']").val()=='disabled') {
              return;
            }
          }

          var handlerSettings = fieldRow.find('td:nth-child(3)').text();
          if(handlerSettings && handlerSettings!='-') {
            return;
          }

          fieldRow.hide();
        });

        var link = $('<a href="#">Show all field settings</a>')
          .click(function(e) {
            forEachSetting({setting:'handler',fields:name},function(fieldHandler) {
              fieldHandler.parents('tr').show();
            });

            link.remove();

            e.preventDefault();
            return false;
          })
          .appendTo(entityTypeHandler.parents('td'));
      });

      // If there are any new handlers, we reset all callbacks because that means we completely replaced the table.
      // Otherwise the callbacks would stack and the performance would decrease with every entity type handler setting
      // that is changed.
      var reset = true;

      forEachSetting({setting:'handler',once:ONCE_ID+'-pool-summary'},function(entityTypeHandler) {
        /*if (entityTypeHandler.val() == 'ignore') {
          return;
        }*/

        if(reset) {
          onPoolAdded = [];
          onPoolRemoved = [];
          reset = false;
        }

        var entityTypeRow = entityTypeHandler.parents('tr');

        function check(column,type) {
          var forbid = [];
          var allow = [];
          var force = [];

          var enabled = column.parents('tr').find("[name$='["+type+"]']").val()!=='disabled';

          column.find('select').each(function () {
            var self = $(this);

            var name = getPoolName(self.attr("name"));

            if(enabled) {
              var action = self.val();
              if (action == 'force') {
                force.push(name);
              } else if (action == 'allow') {
                allow.push(name);
              } else {
                forbid.push(name);
              }
            }
            else {
              if(pools.indexOf(name)>=0) {
                self.val('force');
                force.push(name);
              }
              else {
                self.val('forbid');
                forbid.push(name);
              }
            }
          });

          // They're not all treated identical.
          if(force.length && allow.length) {
            return;
          }

          // If any pool is either ALLOW or FORCE, all pools selected for the Flow must have the same config.
          if(force.length || allow.length) {
            if(!isArrayEqual(pools,force.length?force:allow)) {
              return;
            }
          }

          // They're all treated identical => Only show one select field to change setting for all pools at once.
          column.children().hide();

          var select = $('<select><option value="force">Force</option><option value="allow">Allow</option><option value="forbid">Forbid</option></select>')
            .change(function(e) {
              for(var i=0; i<pools.length; i++) {
                column.find("[name$='["+pools[i]+"]']")
                  .val(select.val());
              }
            })
            .val(force.length ? 'force' : (allow.length ? 'allow' : 'forbid'))
            .appendTo(column);

          var showAll = $('<div><a href="#">Set per Pool</a></div>')
            .click(function(e) {
              select.remove();
              showAll.remove();
              showAll = null;
              select = null;

              column.children().show();

              e.preventDefault();
              return false;
            })
            .appendTo(column);

          onPoolAdded.push( function(pool) {
            if(!select) {
              return;
            }

            column.find("[name$='["+pool+"]']").val(select.val());
          } );

          onPoolRemoved.push( function(pool) {
            if(!select) {
              return;
            }

            column.find("[name$='["+pool+"]']").val("forbid");
          } );
        }

        // Export pools
        check(entityTypeRow.find('td:nth-child(5)'),'export');

        // Import pools
        check(entityTypeRow.find('td:nth-child(9)'),'import');
      });

      applyFlowTypeSetting();
    }
  }

})(jQuery, drupalSettings);
