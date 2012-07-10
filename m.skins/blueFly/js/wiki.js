jQuery(document).ready(function(){

	var btn_srch = $('.btn_srch');
	var srch = $('form.srch');
	btn_srch.click(function(){
	srch.toggle();
	}); 

	var view_rage = 5; 
	var more_btn = $('#more_view');
    var test = 'ul#wiki_document_list>:gt('+(view_rage-1)+')';
	$(test).css('display','none');

	more_btn.click(function(){
		view_rage +=5;
		test = 'ul#wiki_document_list>:lt('+(view_rage)+')';
		$(test).slideDown('slow');
	});


}); // End of ready