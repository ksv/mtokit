jQuery.fn.center = function () {
    this.css("position","absolute");
    this.css("top", Math.max(0, (($(window).height() - this.outerHeight()) / 2) +   $(window).scrollTop()) + "px");
    this.css("left", Math.max(0, (($(window).width() - this.outerWidth()) / 2) + $(window).scrollLeft()) + "px");
    return this;
}

var uploader;
var v_uploader;

$(document).ready(function()
{
  $(".close, .cancel").click(function() {
            //$(this).parents('.img_loader_popup').hide();
            //$('.overlay').hide();
            hideImageLoader();
            
        });

$("#images_container").delegate(".del",'click',function() {
            if (confirm(sayIt('Are you sure?')))
            {
                sendDelete([$(this).attr('rel')],'images_container');
            }    
    });
    
    $("#images_container").delegate(".img_click",'click',function(e) 
        {
            e.preventDefault();
             $('#images_container input[type=checkbox][rel='+$(this).attr('rel')+']').trigger('click');
             //$('#image_loader').hide();
                
        });
    $("#images_container").delegate("input.checkbox[is_media]", "click", function(e){
        if (window.upcbOnImageSelect)
        {
            if (!window.upcbOnImageSelect(collectSelected()))
            {
                e.preventDefault();
                if ($(this).attr("checked"))
                {
                    $(this).removeAttr("checked");
                    $(this).parents(".file:first").find("a").removeClass("checked");
                }
                return false;
            }
        }
    });
    $("#images_container").delegate("input.checkbox", "change", function(e){
        if ($(this).attr("checked"))
        {
            $(this).parents(".file:first").find("a").addClass("checked");
            if (!$("#images_container").hasClass("social-image-uploader"))
            {
                    $('#uploadSubmit').click();
            }
        }
        else
        {
            $(this).parents(".file:first").find("a").removeClass("checked");
        }
    });
    $("#images_container").delegate(".file",'mouseenter',function(e) 
        {
            e.preventDefault();
            $(this).addClass('checked');
            $('.downloaded', this).show(); 
                
        });
        
     $("#images_container").delegate(".file",'mouseleave',function(e) 
        {
            e.preventDefault();
            $(this).removeClass('checked');
            $('.downloaded', this).hide(); 
                
        });    
    


 $("#images_container").delegate('input[type=checkbox]','change', function () 
    {
        $('#uploadSubmit').attr('disabled', false);
    }
);
  
       
$('#uploadSubmit').bind('click', function (){
    if ($('#account').prop('checked'))
    {     
        //collect selected ids
        var ids = new Array();
        $('#images_container input[type=checkbox]:checked').each(function (){
            ids.push($(this).attr('rel')) 
        });
        console.log(ids);

        if (window.callbackDesignsSelected)
        {    
            window.callbackDesignsSelected(ids);
        }; 

        hideImageLoader();
    }    
});    

	if ($(".blck_notupl").length == 1)
	{
		$("#btn_import").bind("click", function(e)
			{
				e.preventDefault();
				$("#image_loader .import-from #Fb").click();
			}
		);
		$("#btn_uplfst").bind("click", function(e)
			{
				e.preventDefault();
				/*$(".blck_notupl").remove();
    $('.files').show();
    $('.full').show();
    $('.nav.nav_popup').show();
    $('.login_true').show();
    $('.buttons-block').show();*/
                    //alert($("#uploader_btn input").length);
                    $(".full").show();
				$("#uploader_btn input").trigger("click");
                                $(".full").hide();
			}
		);
	}
});

    function collectSelected()
    {
        var ids = [];
        $('#images_container input.checkbox:checked').each(function(){
            ids.push($(this).attr("rel"));
        });
        return ids;
    }

function showImageLoader(callback)
{
    console.log('show IL');
    
    $(document).on('scroll', function (e){
        console.log('11');
        e.preventDefault();
        return false;
    });
    $('#image_loader').show(20, function(){ $('#image_loader').center(); });
    $('.overlay').show();
    $('#account').click();
				init_upl_btn();
    $('#images_container').entlist({             
            ctrl:'media',
            get_method:'get_popup_list_json',
            set_params_method:'set_popup_list_params',
            get_pager_method:'get_popup_list_pager',
            list_item_tpl_id: 'popup_list_item_tpl',
            //init_params: '{$#init_params}',            
            pager_container_class: 'paging',
            limit: 4,
            per_page_container: 'plate_select',
            callback: function(ret) 
            {
                $('#images_container').unmask();
													if ($("#images_container .file").length > 0)
													{
														$("#image_loader .blck_notupl").remove();
						        $('.files').show();
						        $('.full').show();
						        $('.nav.nav_popup').show();
						        $('.login_true').show();
						        $('.buttons-block').show();
													}
													else
													{
														$("#image_loader .blck_notupl").css({visibility: "visible"});
													}
                /*if (typeof ret.filters != 'undefined' && typeof ret.filters.folder_id != 'undefined')
                {
                    //$('#images_container').entlistt('set_param',{'folder_id':current_folder});
                    $('#popup_folder').val(ret.filters.folder_id);                      
                }*/
            }
    });

       //var cf = $("#folders_container .folder.selected");
       //var current_folder = cf.attr('rel');

       

    //$('#images_container').load('/media/popup_list');
}


function finishImageLoader()
{
    hideImageLoader();
    if (window.upcbOnFinish)
    {
        window.upcbOnFinish(collectSelected());
    }
}

function hideImageLoader()
{
    $('.overlay').hide();
    $('#image_loader').hide();
    $('#images_container').entlist("destroy");
    if (document.getElementById('designs_list'))
    {
        $('#ent_container').entlist('load');
    }
}

function hideVectorLoader()
{
    $('.overlay').hide();
    $('#vector_loader').hide();    
}

// init button upload
function init_upl_btn()
{
	if ($("#license").prop("checked"))
	{
             uploader = initUploader();
	}
     $("#license").bind('change', function (e){
         //
         if ($(this).prop('checked'))
         {
             $('#uploader_btn input').attr('disabled', false);
             uploader = initUploader();

         } else
         {
             $(this).prop('checked', true);
             e.preventDefault();
             return;
            //$('#uploader_btn input').attr('disabled', true);
         }

     });
}
function showVectorLoader()
{
    hideImageLoader();
    $('#vector_loader').show();
    $('#vector_loader').center();
    $('.overlay').show();
    v_uploader = initVectorUploader();
    console.log(v_uploader);
}

function uploadVFile()
{
    
    console.log($('#vector_form').serializeArray());
    
        $.ajax_postup({
        url: mtoApp.getCfg('upload_url'),
        file_ele_ids:['file_v1'],
        data: $('#vector_form').serializeArray(),
        success:function(data)
        {
           if (data == '1')
           {
               $('.in_load_file .add_file').show();
           } else
           {
               alert(data);
           }    
            
        }

    }
    );
}


function initUploader()
{
   return  new qq.FileUploaderBasic({
        button: document.getElementById('uploader_btn'),
        action: mtoApp.getCfg('upload_url'),
        params: {folder:function (){ return $('#popup_folder').val()}},
        allowedExtensions: mtoApp.getCfg("upload_extensions"),
        allowedMime: mtoApp.getCfg("upload_mime_types"),
        debug: true,
        messages: {
            typeError: "Недопустимый тип файла {file}.  Допустимые типы: {extensions}.",
            sizeError: "{file} слишком велик, максимальный размер файла {sizeLimit}.",
            minSizeError: "{file} слишком мал, минимальный размер файла {minSizeLimit}.",
            emptyError: "{file} пуст.",
            onLeave: "Файлы загружаются, закрытие окна приведет к отмене загрузки."            
        },
          onSubmit: function(id, fileName){
              var tpl = $('#fileo_tepl').html().replace(new RegExp("\\[ID\\]","g"),"tmp"+id).replace("[FILENAME]","").replace("[SIZE]", "");
              $('#images_container').prepend(tpl);
              
          },
          onProgress: function(id, fileName, loaded, total)
          {
                
          },
          onComplete: function(id, fileName, responseJSON)
          {
            
            if (responseJSON.success)
            {
                //replace for real id
                
                //replace src
                //console.log(id);
                $('#img_tmp'+id).attr('src',responseJSON.url);
                $('#size_tmp'+id).html(responseJSON.size+" px");
                $('#filename_tmp'+id).html(responseJSON.filename);
                $('#file_tmp'+id).attr("rel", responseJSON.new_id);
                $('div[rel=li_tmp'+id+'] .cancel_load').addClass('hide');
                if ($(".blck_notupl").length == 1)
																{
																	$(".blck_notupl").remove();
													    $('.files').show();
													    $('.full').show();
													    $('.nav.nav_popup').show();
													    $('.login_true').show();
													    $('.buttons-block').show();
																}
            } else
            {
                alert(responseJSON);
            }    
            

          },
           
          onCancel: function(id, fileName)
          {              
              $('div[rel=li_tmp'+id+']').remove();
          }
    });   
}


function initVectorUploader()
{
   return  new qq.FileUploaderBasic({
        button: document.getElementById('vector_uploader_btn'),
        action: '/media/process_vector_upload',
        params: {num_layers:function (){ return $('input[name=colors]:checked').val()}},
        debug: true,
        allowedExtensions: ['eps'],
        multiple: false,
        immediate: false,
          onSubmit: function(id, fileName){
              //alert(fileName);
              
          },
          onSelect: function(id, fileName){              
              $('div.load_file div.file-name').html(fileName);
              
          },
          onProgress: function(id, fileName, loaded, total)
          {
                
          },
          onComplete: function(id, fileName, responseJSON)
          {
                          
            if (responseJSON.success)
            {
                $('div.load_file div.file-name').html("");
                $('div.in_load_file').show();
                $('div.loader').hide();
                
                
                
            } else
            {
                alert(responseJSON);
            }    
            

          },
          onCancel: function(id, fileName)
          {              
             
          }
    });   
}


function sendDelete(cbx,container_id, callback)
{
    
    $.post("/ajax/call", {service: 'media', method: 'delete_mass', args: {ids:cbx}}, function(data)
    {                    
        if (data == '1')
        {
            $('#'+container_id).entlist('load');   
            if (callback)
            {
                callback();
            }    
            
        } else
        {
            //alert(data);
            console.log(data);
        }
    
    });
    
}

function initImgClickEvents()
{
     // open pop-up img_more
    $(".link_more").click(function () 
    {
        if ($(this).hasClass('noclick')) 
        {
            $(this).removeClass('noclick');
        }
        else 
        {
            $("#img_more").show();
            $("#img_more").attr('rel',$(this).attr('rel'));
            openInfoPopup($(this).attr('rel'));
        }    
        
        return false
    });
    
}



function initImgHoverEvents()
{
    $('.file_cont .del').bind('click', function (evt){
        
        evt.preventDefault();
        var cbx = new Array();
        cbx.push($(this).attr('rel'));   
        if (confirm(sayIt('Are you sure?')))
        {
            sendDelete(cbx, $(this).parents('ul').attr('id'));
        }   
         
    });
    
    // show additional information about a file
    $(".link_more").hover(
        function(){
            $(this).parents(".file_cont").addClass("show");
        }
    );
    // hide additional information about a file
    $(".file_cont").bind("mouseleave",function() 
    {
            $(this).removeClass("show");
    });
} 

function uploaderCheckAll()
{
    $('.file .checkbox').attr('checked', 'checked');
    $('.file .checkbox').each(function(){
        $(this).parents('.file:first').find('a').addClass('checked');
    });
}

function uploaderUncheckAll()
{
    $('.file .checkbox').attr('checked', false);
    $('.file .checkbox').each(function(){
        $(this).parents('.file:first').find('a').removeClass('checked');
    });
}

