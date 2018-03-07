// Binds to notice dismiss button, adds query var to URL
var $j = jQuery.noConflict();
$j(function () {
	$j('.notice.is-dismissible').on('click', '.notice-dismiss', function(event)
	{
	    //var newURLString = window.location.href + '?skipped=1';
	    var url = window.location.href;
	    var param = 'wpcw_perma_hide=1'
	    if(url.indexOf('?')!==-1){
            url+='&'+param;
        }else{
            url+='?'+param;
        }
	    window.location.href = url;
	})
});