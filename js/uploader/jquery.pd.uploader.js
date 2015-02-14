(function($){
    $.fn.pdUploader = function(options)
    {
//plugin =======================================        
        var settings = {
            form_url: mtoApp.getCfg("uri_base")+'/utils/ajax_uploader_form',
            upload_url: mtoApp.getCfg('upload_url'),
            list_url: mtoApp.getCfg("uri_base") + '/media/get_media_list',
            set_params_url: mtoApp.getCfg("uri_base") + '/media/set_popup_list_params',
            delete_url: '/media/delete_mass',
            on_select: null,
            on_check: null,
            pending_caption: 'Загрузка данных....',
            only_types: '',
            embro_product: ''
        };
        if (options)
        {
            $.extend(settings, options);
        }
        
        var cur_media_html = null;
        
        var uploader;
        var v_uploader;
        var frame;
        var cont;
        var bc;
        var social_workers = {
            fb: getWorkerFb(),
            vk: getWorkerVk(),
            instagram: getWorkerInstagram()
        };
        
        var resources = {};
        resources['http://vk.com/js/api/openapi.js'] = {type: "script", loaded: false};
        resources['http://connect.facebook.net/en_US/all.js'] = {type: "script", loaded: false};
        //resources[mtoApp.getCfg("uri_cdn")+'/js/jquery-ui.js'] = {type: "script", loaded: false};
        resources[mtoApp.getCfg("uri_cdn")+'/js/lib/mt.js'] = {type: "script", loaded: false};
        resources[mtoApp.getCfg("uri_cdn")+'/js/lib/jquery.pd.entlist.js'] = {type: "script", loaded: false};
        resources[mtoApp.getCfg("uri_cdn")+'/js/uploader/fileuploader.js'] = {type: "script", loaded: false};
        resources[mtoApp.getCfg("uri_cdn")+'/js/uploader/instagram.js'] = {type: "script", loaded: false};
        resources[settings['form_url']] = {type: "html", loaded: false};
        var loaded = false;
        
        load();
        
        
        
        return $(this).click(function(e){
            e.preventDefault();
            open();
        });
        //===================================================
        
        
        /*
         * open handler
         **/
        function open()
        {
            //console.log('OPEN');
            $(window).on('scroll',disableScroll);
            
            if (!loaded)
            {
                return;
            }
            frame.show(20, function(){ frame.center(); });
            $('.overlay').show();
            cont.entlist({
                load_url: settings['list_url']+'?only_types='+settings['only_types']+'&embro_product='+settings['embro_product'],
                set_params_url: settings['set_params_url'],
                //get_pager_method:'get_popup_list_pager',
                list_item_tpl_id: 'popup_list_item_tpl',
                //init_params: '{$#init_params}',            
                //pager_container_class: 'paging',
                limit: 24,
                next_btn: $('#lnext'),
                next_btn_loading: $('#lnext_loading'),
                paging_method: 'infinite',
                //per_page_container: 'plate_select',
                callback: function(ret){
                    cont.unmask();
                    cur_media_html = cont.html();
                    if (settings['embro_product'] != '')
                    {
                        $('#uploader_btn').hide();
                    }    
                    if (ret.all_num > 0)
                    {
                        $(".blck_notupl", frame).remove();
                        $('.files', frame).show();
                        $('.full', frame).show();
                        $('.nav.nav_popup', frame).show();
                        $('.login_true', frame).show();
                        $('.buttons-block', frame).show();
                    }
                    else
                    {
                        $(".blck_notupl", frame).css({visibility: "visible"});
                    }
                    init_after_load_events();
                    /*if (typeof ret.filters != 'undefined' && typeof ret.filters.folder_id != 'undefined')
                    {
                        //$('#images_container').entlistt('set_param',{'folder_id':current_folder});
                        $('#popup_folder').val(ret.filters.folder_id);                      
                    }*/
                }
            });
            $('#account', frame).click();
            init_upl_btn();
        }
        
        function open_vector()
        {
            if (!loaded)
            {
                return;
            }
            close();
            $('#vector_loader', frame).show();
            $('#vector_loader', frame).center();
            $('.overlay').show();
            v_uploader = init_vector_uploader();
            console.log(v_uploader);
        }
        
        function init_after_load_events()
        {
            $(".link_more").click(function() {
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
            $('.file_cont .del').bind('click', function (evt){

                evt.preventDefault();
                var cbx = new Array();
                cbx.push($(this).attr('rel'));   
                if (confirm(sayIt('Are you sure?')))
                {
                    remove(cbx, $(this).parents('ul').attr('id'));
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
            });
        }
        
        
        function init_upl_btn()
        {
            if ($("#license", frame).prop("checked"))
            {
                 uploader = init_uploader();
            }
            $("#license", frame).bind('change', function (e){
                if ($(this).prop('checked'))
                {
                    $('#uploader_btn input', frame).attr('disabled', false);
                    uploader = init_uploader();

                } else
                {
                    $(this).prop('checked', true);
                    e.preventDefault();
                    return;
                }

            });
        }
        
        function init_uploader()
        {
           return  new qq.FileUploaderBasic({
                button: document.getElementById('uploader_btn'),
                action: settings['upload_url'],
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
                    cont.prepend(tpl);

                },
                onProgress: function(id, fileName, loaded, total)
                {

                },
                onComplete: function(id, fileName, responseJSON)
                {
                    if (responseJSON.success)
                    {
                        $('#img_tmp'+id).attr('src',responseJSON.url);
                        $('#size_tmp'+id).html(responseJSON.size+" px");
                        $('#filename_tmp'+id).html(responseJSON.filename);
                        $('#show_filename_tmp'+id).html(responseJSON.filename);
                        $('#file_tmp'+id).attr("rel", responseJSON.new_id);
                        $('div[rel=li_tmp'+id+'] .cancel_load').addClass('hide');
                        $('div[rel=li_tmp'+id+'] a:first').attr('rel', responseJSON.new_id);
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
                        var reason = typeof(responseJSON.reason) != "undefined" ? responseJSON.reason : 0;
                        switch (reason)
                        {
                            case 1:
                                alert("Загрузка в одну папку медиакорзины более 1000 изображений невозможна");
                            break;
                            default:
                                alert("Произошла ошибка при загрузке изображения");
                            break;
                        }
                        $('#img_tmp'+id).parents(".file:first").remove();
                    }    
                },
                onCancel: function(id, fileName)
                {              
                    $('div[rel=li_tmp'+id+']').remove();
                }
            });   
        }
        
        function init_vector_uploader()
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
        
        
        function onload()
        {
            frame = $('#image_loader');
            cont = $('#images_container', frame);
            bc = $('#social_album', frame);
            $(".close, .cancel").click(function() {
                close();
            });
            cont.delegate(".del",'click',function() {
                if (confirm(sayIt('Are you sure?')))
                {
                    remove([$(this).attr('rel')],cont);
                }    
            });
            //console.log('ONLOAD DELEGATE STUFF');
            cont.delegate(".img_click",'click',function(e) 
            {
                //console.log('IMG CLICK ');
                
                e.preventDefault();
                $('input[type=checkbox][rel='+$(this).attr('rel')+']', cont).trigger('click');
                if (!cont.hasClass("social-image-uploader"))
                {
                    $('#uploadSubmit', frame).click();
                }
            });
            cont.delegate("input.checkbox[is_media]", "click", function(e){
                if (settings['on_check'])
                {
                    if (!settings['on_check'](get_selected()))
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
            cont.delegate("input.checkbox", "change", function(e){
                if ($(this).attr("checked"))
                {
                    $(this).parents(".file:first").find("a").addClass("checked");
                }
                else
                {
                    $(this).parents(".file:first").find("a").removeClass("checked");
                }
            });
            cont.delegate(".file",'mouseenter',function(e){
                    e.preventDefault();
                    $(this).addClass('checked');
                    $('.downloaded', this).show(); 
            });
            cont.delegate(".file",'mouseleave',function(e){
                e.preventDefault();
                $(this).removeClass('checked');
                $('.downloaded', this).hide(); 
            });    
            cont.delegate('input[type=checkbox]','change', function(){
                   $('#uploadSubmit').attr('disabled', false);
            });
            $('#uploadSubmit', frame).bind('click', function (){
                if ($('#account', frame).prop('checked'))
                {     
                    var ids = get_selected();
                    console.log(ids);
                    if (settings['on_select'])
                    {
                        settings['on_select'](ids);
                    }
                    close();
                }    
            });  
            $('#importSubmit', frame).bind('click', function(){

                cont.find('input:checkbox').each(function(){
                    if (this.checked)
                    {
                        $.post(settings['upload_url'], {import_url: $(this).val(), folder: $("#popup_folder", frame).val()}, function(dat){
                            var tpl = $('#fileo_tepl').html().replace(new RegExp("\\[ID\\]","g"),"tmp"+dat.new_id).replace("[FILENAME]",dat.filename).replace("[SIZE]", dat.size);
                            cont.prepend(tpl);
                            $('#img_tmp'+dat.new_id).attr('src',dat.url);
                            $('#file_tmp'+dat.new_id).attr("rel", dat.new_id);
                            $('div[rel=li_tmp'+dat.new_id+'] .cancel_load').addClass('hide');
                            if ($(".blck_notupl", frame).length == 1)
                            {
                                $(".blck_notupl", frame).remove();
                                $('.files', frame).show();
                                $('.full', frame).show();
                                $('.nav.nav_popup', frame).show();
                                $('.login_true', frame).show();
                                $('.buttons-block', frame).show();
                            }
                        }, "json");
                    }
                });
                $('#account', frame).attr('checked', true);
                cont.html(cur_media_html);
                switch_tab(false, null);

                //cont.entlist('load');
                return false;
                
            });
            if ($(".blck_notupl", frame).length == 1)
            {
		$("#btn_import", frame).bind("click", function(e){
                    e.preventDefault();
                    $(".import-from #Fb", frame).click();
                });
		$("#btn_uplfst").bind("click", function(e){
                    e.preventDefault();
                    $(".full").show();
                    $("#uploader_btn input", frame).trigger("click");
                    $(".full").hide();
		});
            }
            //            $(".btn-success", frame).click(function(e){
            //                e.preventDefault();
            //                finish();
            //            });
            $("a[rel=check_all]", frame).click(function(e){e.preventDefault(); check_all(true)});
            $("a[rel=uncheck_all]", frame).click(function(e){e.preventDefault(); check_all(false)});
            VK.UI.button('vk-login');
            $('#vk-login').hide();
            $('#fb-login').hide();
            $('#instagram-login').hide();

            $('#vk-login').on('click', function(){
                VK.Auth.login(null, VK.access.PHOTOS);
            });
            $('input[type=radio][name=import]').bind('change', function()
            {
                cont.empty();
                bc.empty();
                cont.mask(settings['pending_caption']);

                var node = $(this).attr('id');
                (node == 'Vk' || node == 'Fb' || node == 'Instagram') ? switch_tab(true, node) : switch_tab(false, node);

                switch (node) {
                    case 'Fb':
                        cont.entlist('unbind_scroll');
                        social_status(social_workers.fb);
                        break;
                    case 'Vk':
                        cont.entlist('unbind_scroll');
                        social_status(social_workers.vk);
                        break;
                    case 'Instagram':
                        cont.entlist('unbind_scroll');
                        social_status(social_workers.instagram);
                        break;
                    default:
                        cont.entlist('unbind_scroll');
                        cont.entlist('bind_scroll');
                        cont.entlist('reload');
                        break;          
                }
            });
            
            $("#license-lite", frame).bind('change', function (e){
                if ($(this).prop('checked'))
                {
                    $('#importSubmit', frame).removeAttr('disabled');
                } else {
                    $(this).prop('checked', true);
                    e.preventDefault();
                    return;
                }
            });
            social_workers.vk.init(function(){social_status(social_workers.vk)});
            social_workers.fb.init(function(){social_status(social_workers.fb)});
            social_workers.instagram.init(function(){social_status(social_workers.instagram)});
            
        }

        function close()
        {
            //console.log('close');
            $('.overlay').hide();
            frame.hide();
            cont.entlist("destroy");
            $(window).off('scroll', disableScroll);
            /*if (document.getElementById('designs_list'))
            {
                $('#ent_container').entlist('reload');
            }*/
        }
        
        function finish()
        {
            close();
//            if (window.upcbOnFinish)
//            {
//                window.upcbOnFinish(collectSelected());
//            }
        }
        
        function close_vector()
        {
            $('.overlay').hide();
            $('#vector_loader').hide();    
        }
        
        function get_selected()
        {
            var ids = [];
            $('input.checkbox:checked', cont).each(function(){
                ids.push($(this).attr("rel"));
            });
            return ids;
        }
        

        function remove(cbx,container, callback)
        {
            $.post(settings['delete_url'], {ids:cbx}, function(data)
            {                    
                if (data == '1')
                {
                    container.entlist('reload');   
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
        
        function check_all(check)
        {
            $('.file .checkbox').attr('checked', check ? 'checked' : false);
            $('.file .checkbox').each(function(){
                if (check)
                {
                    $(this).parents('.file:first').find('a').addClass('checked');
                }
                else
                {
                    $(this).parents('.file:first').find('a').removeClass('checked');
                }
            });
        }
        
        function switch_tab(state, node)
        {
            if (state) 
            {
                cont.addClass('social-image-uploader');
                $('#images_container_bottom').hide();
                $('.full').hide(); 
                $('.lite').show(); 
                $('.lite_lic').show();
                       $('.files').show();
                $('.blck_notupl').hide();
                $('.nav.nav_popup').hide();
                $('.login_true').hide();
                $('.buttons-block').hide();
                $('#crumbs_root').html(node);
            }
            else
            {
                cont.removeClass('social-image-uploader');
                $('#images_container_bottom').show();
                $('.lite').hide();
                $('.lite_lic').hide();
                if ($('.blck_notupl').length == 1)
                {
                    $('.blck_notupl').show();
                    $('.files').hide();
                    $('.full').hide();
                    $('.nav.nav_popup').hide();
                    $('.login_true').hide();
                    $('.buttons-block').hide();
                }
                else
                {
                    $('.files').show();
                    $('.full').show();
                    $('.nav.nav_popup').show();
                    $('.login_true').show();
                    $('.buttons-block').show();
                }
                $('#vk-login').hide();
                $('#fb-login').hide();
                $('#instagram-login').hide();
            }
        }

        function load()
        {
            for (var uri in resources)
            {
                //alert("loading: " + uri);
                load_res(uri, resources[uri]['type']);
            }
        }
        
        function load_res(uri, type)
        {
            if (typeof(type) != "undefined" && type == "script")
            {
                $.getScript(uri, function(){
                    resources[uri]['loaded'] = true;
                    load_check();
                })
            }
            else
            {
                $.get(uri, {}, function(res){
                    $(document.body).append(res);
                    resources[uri]['loaded'] = true;
                    load_check();
                });
            }
        }
        
        function load_check()
        {
            var str = "";
            for (var uri in resources)
            {
                str += uri + ":" + resources[uri]['loaded'] + ";";
                if (!resources[uri]['loaded'])
                {
                    loaded = false;
                    return;
                }
            }
            loaded = true;
            onload();
        }
        
//social handling
        function social_status(worker)
        {
            for (var k in social_workers) 
            {
                $(social_workers[k].button).hide();
            }

            var response_type = (bc.html() == '') ? 'get_albums' : 'get_images';
            worker.get_status(response_type, function() {
                //debug
                //console.log('[node.get_status]', this instanceof Boolean);
                if (this == false) 
                {
                    cont.empty();
                    $('#logout_link', frame).hide();
                    $(worker.button).show();
                    $('.crumbs').hide();
                    $('#help_notice').hide();
                    $('.selector').hide();
                } 
                else 
                {
                    bc.empty();
                    $('.crumbs').show();
                    $('#help_notice').show();
                    $('.selector').show();
                    cont.mask(settings['pending_caption']);
                    $('#logout_link', frame)
                        .html('Вы авторизованы в ' + worker.displayName + ' <a href>выйти</a>')
                        //.unbind('click')
                        .show();

                    $('#logout_link a', frame).bind('click', function(e){
                        e.preventDefault();
                        bc.empty();
                        cont.mask(settings['pending_caption']);
                        worker.logout(function(){
                            social_status(worker);
                        });
                    });
                    social_render()[response_type](worker, this);
                }

            });
            
        }
        
        function social_render()
        {
            return {
                get_albums : function(worker, data) {
                    cont.unmask();
                    cont.html(tmpl("popup_social_albums_tpl", {list : data}));
                    cont.find('.file').bind('click', function(){
                        bc.empty();
                        cont.mask(settings['pending_caption']);
                        var aid = $(this).attr('rel');
                        bc.html('&raquo; ' + $('#album_name', this).html() );
                        worker.get_images(aid, function(){
                            social_render().get_images(worker, this);
                        });
                    });

                    $('.crumbs a').unbind('click').bind('click', function(event){
                        event.preventDefault();
                    });

                    //console.log('albums of', worker.name, data);
                },

                get_images : function(worker, data) {
                    var self = this;
                    cont.unmask();
                    cont.html(tmpl("popup_social_photos_tpl", {list : data}));
                    cont.find('.file').bind('click', function(e){
                        var file = $(this),
                            input_id = '#img_' + $(this).attr('rel'),
                            target_class = $(e.target).attr('class');

                        if ( target_class == 'file' || target_class == 'img_click' || target_class == 'omg') {
                            //var state = $(input_id).attr('checked') ? false : true;
                            //$(input_id).attr('checked', state);
                            $(input_id).trigger("click");
                            //file.toggleClass('checked', state);
                        }

                    });

                    cont.find('input', '.file').bind('click', function(e){
                        var state = $(this).attr('checked') ? true : false;
                        $(this).parent().parent().toggleClass('checked', state);
                    });

                    $('.crumbs a').bind('click', function(event){
                        event.preventDefault();
                        bc.empty();
                        cont.mask(settings['pending_caption']);
                        social_status(worker);
                    });

                    $('#images_container').parent().on('scroll', function() { self.onScroll(this, worker) });
                    //console.log('photos', data);
                },

                onScroll: function(el, worker) {
                    self = this;
                    if (self.waiting) return;
                    if ($(el).scrollTop() + $(el).innerHeight() >= $(el)[0].scrollHeight)
                    {
                        self.waiting = true;
                        $('#lnext_loading').show();
                        worker.next(function(){
                            self.waiting = false;
                            $('#lnext_loading').hide();
                            var data = this;
                            for (var i = 0; i < data.length; i++) 
                            {
                                var element = tmpl("popup_social_photos_tpl", {list : [data[i]]});
                                cont.append(element);
                                var next_rel = cont.children().size();
                                var next_id = 'img_' + next_rel;
                                $("#images_container .file:last-child").attr("rel", next_rel);
                                $("input", "#images_container .file:last-child").attr('id', next_id);
                                $("#images_container .file:last-child").on('click', function(e){
                                    var input_id = '#img_' + $(this).attr('rel'),
                                        target_class = $(e.target).attr('class');
                                    if ( target_class == 'file' || target_class == 'img_click' || target_class == 'omg') {
                                        $(input_id).trigger("click");
                                    };
                                });
                                $("input", "#images_container .file:last-child").on('click', function(e){
                                    var state = $(this).attr('checked') ? true : false;
                                    $(this).parent().parent().toggleClass('checked', state);
                                });
                            };
                            
                        });
                    }
                }
                
            };
        }
        
        
//social workers ============================        
        
        
        function getWorkerVk()
        {
            var worker = function(){
                this.name = 'vk';
                this.displayName = 'Vkontakte';
                this.appId = mtoApp.getCfg("vk_id");
                this.button = '#vk-login';
            };
            worker.prototype = {

                _query : function() {
                    return;
                },

                init : function(callback){
                    VK.init({
                        apiId : this.appId,
                        status : true
                    });

                    VK.Observer.subscribe('auth.login', function(){
                        if (typeof callback == 'function') {
                            callback.call();
                        }
                    });
                },

                get_status : function(query_type, callback){
                    var $self = this;
                    VK.Auth.getLoginStatus(function(response) {
                        // debug
                        //console.info('[getLoginStatus]:', response.status);

                        if (response.session && response.status === 'connected') 
                        {
                            $self.mid = response.mid;
                            $self._query = $self[query_type];
                            $self._query(function(){
                                callback.call(this);
                            });
                        } 
                        else 
                        {
                            callback.call(false);
                        }
                    });           
                },

                logout : function(callback) {
                    VK.Auth.logout(function(){
                        if (typeof callback == 'function') {
                            callback.call();
                        }
                    });
                },

                get_images : function(aid, callback){
                    VK.Api.call('photos.get', {
                        uid : this.mid,
                        aid : aid
                    }, function(raw){

                        // debug
                        //console.log('[photos.get]', raw);

                        if (this.error != undefined) {
                            callback.call(false);
                            return;
                        };

                        var data = raw.response;
                        data.splice(0,1);

                        callback.call( $(data).map(function()
                        {
                            if (this.src_xxbig !== undefined) {
                                var bigsize = this.src_xxbig;
                            } 
                            else if(this.src_xbig !== undefined) {
                                var bigsize = this.src_xbig;  
                            }
                            else {
                                var bigsize = this.src_big; 
                            }

                            return {
                                thumb : this.src,
                                big : bigsize
                            };
                        }));
                    });
                },

                get_albums : function(callback){
                    // debug
                    /*VK.Api.call('photos.getAlbumsCount', {uid:this.mid}, function(raw) {
                        console.log('[photos.getAlbumsCount]:', raw.response);
                    });*/

                    VK.Api.call('photos.getAlbums', {
                        uid : this.mid,
                        need_covers : 1
                    }, function(raw){

                        if (raw.error != undefined) {
                            throw raw.error;
                            callback.call(false);
                            return;
                        };

                        var parsed = $(raw.response).map(function(){
                            if (this.size > 0) {
                                return {
                                    aid : this.aid,
                                    thumb : this.thumb_src,
                                    title : this.title
                                };
                            };
                        });

                        callback.call(parsed);
                    });
                },

                onScroll: function(event) {
                    //
                }
                
            };
            return new worker;
        }
        
        function getWorkerFb()
        {
            var worker = function()
            {
                this.name = 'fb';
                this.displayName = 'Facebook';
                this.appId = mtoApp.getCfg("fb_uploader_id");
                this.button = '#fb-login';
            };
            worker.prototype = {
                _query : function() {
                    return;
                },

                init : function(callback){
                    FB.init({
                        appId : this.appId,
                        xfbml : true,
                        oauth : true,
                        status : true,
                        cookie : true
                    });

                    FB.Event.subscribe('auth.login', function(response){
                        if (typeof callback == 'function') {
                            callback.call();    
                        }
                    });
                },

                get_status : function(query_type, callback){
                    var $self = this;
                    FB.getLoginStatus(function(response) {
                        if (response.status === 'connected') 
                        {
                            $self._mid = response.mid;
                            $self._query = $self[query_type];
                            $self._query(function(){
                                callback.call(this);
                            });
                        }
                        else
                        {
                            callback.call(false);
                        }
                    });
                },

                logout : function(callback){
                    FB.logout(function(response){
                        if (typeof callback == 'function'){
                            callback.call();
                        }
                    });
                },

                get_images : function(aid, callback){
                    FB.api({
                        method : 'fql.query',
                        query : 'SELECT src,images FROM photo WHERE aid = \'' + aid + '\''
                    }, function(response){

                        if (!response || response.error) {
                            callback.call(false);
                            return;
                        };
                        var parsed = $(response).map(function(){
                            return {
                                thumb : this.src,
                                big : this.images[0].source
                            }
                        });

                        callback.call(parsed);
                    });
                },

                get_albums : function(callback){
                    FB.api({
                        method: 'fql.multiquery',
                        queries: {
                            query1: 'SELECT aid,name,link,photo_count,cover_object_id FROM album WHERE owner = me() AND photo_count > 0',
                            query2: 'SELECT pid,src FROM photo WHERE object_id IN (SELECT cover_object_id FROM #query1)'
                            //query3: 'SELECT pid,src FROM photo WHERE owner = me()'
                        }
                    }, function(response){


                        if (!response || response.error) {
                            callback.call(false);
                            return;
                        };

                        var parsed = new Array();

                        $(response[0].fql_result_set).each(function(index, value){
                            var result = {
                                aid : value.aid,
                                title : value.name,
                                thumb : response[1].fql_result_set[index].src
                            };

                            parsed.push(result);
                        });
                        callback.call(parsed);
                    });
                },

                onScroll: function(event) {
                    //
                }
                
            };
            return new worker;
        }

        function getWorkerInstagram()
        {
            var worker = function() {
                this.name = 'Instagram';
                this.displayName = 'Instagram';
                this.appId = mtoApp.getCfg("instagram_id");
                this.landing = mtoApp.getCfg("instagram_landing");
                this.button = '#instagram-login';
                this.next_url = false;
            };
            worker.prototype = {
                _query: function() {
                    return;
                },
                init: function(callback) {
                    Instagram.init({
                        appid: this.appId,
                        landing: this.landing,
                        status: true
                    });
                    Instagram.subscribe('login', function(){
                        if (typeof callback == 'function') {
                            callback.call();
                        };
                    });
                },
                logout: function(callback) {
                    Instagram.logout(function(){
                        if (typeof callback == 'function') {
                            callback.call();
                        };
                    });
                },
                get_status: function(query_type, callback) {
                    var self = this;
                    var status = Instagram.getStatus();
                    if (!status) {
                        callback.call(false);
                    } else {
                        self._mid = status;
                        self._query = self[query_type];
                        self._query(function(){
                            callback.call(this);
                        });
                    };
                },
                get_images: function(aid, callback) {
                    var self = this;
                    Instagram.getRecent(self.next_url, function(response){
                        if (response.meta.code != '200') {
                            callback.call(false);
                            return;
                        };
                        self.next_url = response.pagination.next_url ? response.pagination.next_url : false;
                        var parsed = new Array();
                        for (var i = 0; i < response.data.length; i++) {
                            parsed.push({
                                thumb: response.data[i].images.thumbnail.url,
                                big: response.data[i].images.standard_resolution.url
                            });
                        };
                        callback.call(parsed);
                    });
                },
                get_albums: function(callback) {
                    this.next_url = '';
                    Instagram.getInfo(function(response){
                        if (response.meta.code != '200') {
                            callback.call(false);
                            return;
                        };
                        var media_count = response.data.counts.media;
                        var parsed = [{
                            aid : 0,
                            title : response.data.username + ' ('+media_count+' фото) ',
                            thumb : response.data.profile_picture
                        }];
                        callback.call(parsed);
                    });
                },
                next: function(callback) {
                    if (this.next_url) {
                        this.get_images('', callback);
                    } else {
                        callback.call(false);
                    }
                }
            };
            return new worker;
        };
        
    }
    
    $.fn.center = function () 
    {
        this.css("position","absolute");
        this.css("top", Math.max(0, (($(window).height() - this.outerHeight()) / 2) +   $(window).scrollTop()) + "px");
        this.css("left", Math.max(0, (($(window).width() - this.outerWidth()) / 2) + $(window).scrollLeft()) + "px");
        return this;
    }
    
    
})(jQuery);


function disableScroll()
{
    window.scroll(0,0);
}

