var sniDescription;
(function($) {

sniDescription = {

	init : function() {
		$('.closable').children('.panel-top').click(function() {
			$(this).siblings('.panel-body').parent().toggleClass('closed');
		});
	}

};

$(document).ready(function($){ sniDescription.init(); });

})(jQuery);
