(function($){
  $(function(){
    // Tiny UI niceties: focus outlines and card hovers
    $('.fpdqr-panel input, .fpdqr-panel select').on('focus', function(){
      $(this).css('box-shadow','0 0 0 2px rgba(37,99,235,.35)');
    }).on('blur', function(){
      $(this).css('box-shadow','none');
    });
    $('.fpdqr-card').hover(function(){
      $(this).css('box-shadow','0 6px 24px rgba(0,0,0,.25)');
    }, function(){
      $(this).css('box-shadow','none');
    });
  });
})(jQuery);
