
(function($, Drupal, document, dragula) {
  "use strict";

  var dropdownMenus = [];

  /**
   * Drupal behavior, find pages, spawn them, attach their behaviours.
   */
  Drupal.behaviors.layoutEdit = {
    attach: function(context, settings) {

      var token = settings.layout.token;
      var baseurl = settings.layout.baseurl;

      // Emulates bootstrap dropdowns.
      $(context).find('.layout-menu').once('layout-menu', function () {
        var parent = $(this);
        var link = parent.find('> a');
        var child = parent.find('> ul');
        if (child.length) {
          child.hide();
          dropdownMenus.push(parent);
          link.click(function (event) {
            event.preventDefault();
            child.show();
          });
        }
      });

      // Close dropdowns handler
      $(document).click(function (event) {
        dropdownMenus.forEach(function (element) {
          if (!$.contains(element.get(0), event.target)) {
            element.find('> ul').hide();
          }
        });
      });

      // Go for the drag and drop.
      // @todo disabled for now, there are too many bugs.
      if (dragula) {
        var $context = $(context);
        $context.find('[data-contains=0]').once('drag', function () {
          // Ensure this is a top level container
          var topLevel = $(this);
          var layoutId = $(this).data('id');

          // Find all nested containers, nowing that the top level container
          // itself is a container, which must behave like the others.
          var containers = [this];
          topLevel.find('[data-contains=1]').each(function () {
            containers.push(this);
          });

          // Aaaaaannd enable it!
          var drake = dragula(containers, {
            isContainer: function (element) {
              return element.hasAttribute('data-contains');
            },
            invalid: function (element, handle) {
              var item = $(element).closest('[data-item]');
              if (true) { // @todo enable or disable debug
                if (!item.length) {
                  console.log("could not find a parent");
                }
              }
              return !item.length;
            },
            revertOnSpill: true,
            removeOnSpill: false,
            direction: 'vertical'
          });

          drake.on('drop', function (element, target, source, sibling) {
            // Cancel disallowed movess
            if (!element.hasAttribute('data-id') || !target.hasAttribute('data-id')) {
              return drake.cancel(true);
            }

            // Find item and container identifier
            var itemId = element.getAttribute('data-id');
            var containerId = target.getAttribute('data-id');

            // Find the new item position
            var position = 0;
            if (sibling && sibling.hasAttribute('data-id')) {
              // We are not the first, then find out what is the real position
              var nodes = element.parentNode.childNodes;
              for(var i = 0; i < nodes.length; i++) {
                if (nodes[i].hasAttribute('data-id')) {
                  if (nodes[i] === element) {
                    break; // Found ourselves
                  }
                  position++;
                }
              }
            }

            console.log("item: " + itemId + "container: " + containerId + " position: " + position);

            $.ajax(baseurl + 'layout/ajax/move', {
              cache: false,
              method: 'GET',
              success: function (data) {
                console.log("yay");
              },
              error: function () {
                console.log("oups, drag did not go well");
                drake.cancel(true);
              },
              data: {
                tokenString: token,
                layoutId: layoutId,
                containerId: containerId,
                itemId: itemId,
                newPosition: position
              }
            });
          });
        });
      }
    }
  };

}(jQuery, Drupal, document, dragula));
