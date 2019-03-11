function SearchEngineManifest(){
	jQuery('#SearchEngineManifest ul li:has(ul)').addClass('expand').find('ul').hide();
	jQuery('#SearchEngineManifest ul li.expand>h3, #SearchEngineManifest ul li.expand>strong, ').after('<span class="plusMinus">[ + ]</span>');
	jQuery('#SearchEngineManifest ul').on(
		'click',
		'li.collapse > span.plusMinus ',
		function (e) {
			jQuery(this).text('[ + ]').parent().addClass('expand').removeClass('collapse').find('>ul').slideUp();
			e.stopImmediatePropagation();
		}
	);

	jQuery('#SearchEngineManifest ul').on(
		'click',
		'li.expand > span.plusMinus',
		function (e) {
			jQuery(this).text('[ - ]').parent().addClass('collapse').removeClass('expand').find('>ul').slideDown();
			e.stopImmediatePropagation();
		}
	);
	jQuery('#SearchEngineManifest ul').on(
		'click',
		'li.collapse li:not(.collapse)',
		function (e) {
			e.stopImmediatePropagation();
		}
	);
}
