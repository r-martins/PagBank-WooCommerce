jQuery(document).ready(function($) {

    //region Navigation Tabs
    $('.nav-tab-wrapper a').click(function(e) {
        e.preventDefault();
        $('.nav-tab-wrapper a').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        $('.tab-content').hide();
        var selected_tab = $(this).attr('href');
        $(selected_tab).fadeIn();
        //update hash
        window.location.hash = selected_tab;
    });

    //load current tab from #
    var hash = window.location.hash;
    if (hash) {
        $('.nav-tab-wrapper a[href="' + hash + '"]').click();
    }
    //endregion
});