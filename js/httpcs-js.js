function showCreation(){
    jQuery('#connectionContainer').css('position','absolute').fadeOut(function () {
        jQuery('#connectionContainer').css('position','initial');
    });
    jQuery('#creationContainer').css('position','absolute').fadeIn(function(){
        jQuery('#creationContainer').css('position','initial');
    });
}
function showConnexion(){
    jQuery('#creationContainer').css('position','absolute').fadeOut(function(){
        jQuery('#creationContainer').css('position','initial');
    });
    jQuery('#connectionContainer').css('position','absolute').fadeIn(function(){
        jQuery('#connectionContainer').css('position','initial');
    });
}
(function($) {
    $( document ).ready(function() {
        $( "#coClick" ).click(function() {
            showConnexion();
            $('.notice-dismiss').click();
        });
        $( "#creaClick" ).click(function() {
            showCreation();
            $('.notice-dismiss').click();
        });
    });    
})( jQuery );