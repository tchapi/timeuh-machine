$(document).ready(function(){
  $('.music.cards .image').dimmer({
    on: 'hover'
  });

  $('.music.cards').visibility({
    once: false,
    // update size when new content loads
    observeChanges: true,
    // load content on bottom edge visible
    onBottomVisible: function() {
      // loads a max of 5 times
      //loadMoreCards();
    }
  });
});
