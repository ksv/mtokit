$(document).ready(function(){

    // social import
    mySocial = new mySocial();
    mySocial.subscribe(nodeVk);
    mySocial.subscribe(nodeFb);

    VK.UI.button('vk-login');
    $('#vk-login').hide();
    $('#fb-login').hide();

    $('#vk-login').on('click', function(){
        VK.Auth.login(null, VK.access.PHOTOS);
    });

    $('input[type=radio][name=import]').bind('change', function(){

        ShowProgress();
        //mySocial.uploadSubmit.attr('disabled', 'disabled');
        mySocial.breadcrumbs.empty();

        var node = $(this).attr('id');
        (node == 'Vk' || node == 'Fb') ? switchImportTab(true, node) : switchImportTab(false, node);

        switch (node) {
            case 'Fb':
                mySocial.status(nodeFb);
                break
            case 'Vk':
                mySocial.status(nodeVk);
                break
            default:
                mySocial.container.entlist('load');
                break          
        }
    });

    // license apply
    $("#license-lite").bind('change', function (e){
        if ($(this).prop('checked'))
        {
            $('#importSubmit').removeAttr('disabled');
        } else {
            $(this).prop('checked', true);
            e.preventDefault();
            return;
        }
    });

});
  
function checkAll(container_id)
{
    $(container_id).find('.file').each(function(){
        $(this).addClass('checked');
        $(this).find('input').attr('checked', true);
    });
}


function uncheckAll(container_id)
{
    $(container_id).find('.file').each(function(){
        $(this).removeClass('checked');
        $(this).find('input').attr('checked', false);
    });
}