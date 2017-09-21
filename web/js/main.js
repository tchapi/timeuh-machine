var currentPage = 1;
var canFetch = true;
var offset = 400; // px, to load content a bit in advance

$(document).ready(function(){

  // Taken from :
  // https://medium.com/talk-like/detecting-if-an-element-is-in-the-viewport-jquery-a6a4405a3ea2
  $.fn.isInViewport = function() {
    var elementTop = $(this).offset().top;
    var elementBottom = elementTop + $(this).outerHeight();

    var viewportTop = $(window).scrollTop();
    var viewportBottom = viewportTop + $(window).height();
    return elementBottom > viewportTop && elementTop - offset < viewportBottom;
  };

  if ($("#loader").length > 0) {
    $(window).on('resize scroll', function() {
        if ($("#loader").isInViewport() && canFetch) {
          canFetch = false;
          currentPage = currentPage + 1;
          $.ajax({url: $("#loader").attr("data-href") + currentPage, success: function(result){
            $(".music.cards").append(result);
            canFetch = true
            $('.music.cards .image').dimmer({
              on: 'hover'
            });
          }});
        }
    });
  }

  $('.music.cards .image').dimmer({
    on: 'hover'
  });

  $('.message .close').on('click', function() {
    $(this).closest('.message').transition('fade');
  });

});
