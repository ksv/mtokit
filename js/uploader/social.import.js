/**
 * Fetching images from social services.
 *
 * supports: vk.com, fb.com
 * requires:
 *      - jQuery v1.7.2 or more 
 *      - http://vkontakte.ru/js/api/openapi.js
 *      - http://connect.facebook.net/en_US/all.js
 *
 * (c): 2012 Golenischev Alexandr
 * email: createspy@gmail.com
 */

var mySocial = function(){
    this.collection = {};
    this.container = $('#images_container');
    this.albums_tmpl = 'popup_social_albums_tpl';
    this.photos_tmpl = 'popup_social_photos_tpl';
    this.breadcrumbs = $('#social_album');
    this.logout_div = $('#logout_link');
    this.uploadSubmit = $('#uploadSubmit');
};

mySocial.prototype = {

    subscribe : function(obj){
        this.collection[obj.name] = obj;

        obj.init(function(){
            mySocial.status(obj);
        });
    },

    status : function(node){

        for (k in this.collection) 
        {
            $(this.collection[k].button).hide();
        }

        this.response_type = (this.breadcrumbs.html() == '') ? 'get_albums' : 'get_images';
        node.get_status(this.response_type, function() {

            //debug
            //console.log('[node.get_status]', this instanceof Boolean);

            if (this == false) {

                mySocial.container.empty();
                mySocial.logout_div.hide();
                $(node.button).show();

            } else {

                ShowProgress();
                mySocial.logout_div
                    .html('Вы авторизованы в ' + node.displayName + ' <a href>выйти</a>')
                    //.unbind('click')
                    .show();

                $("a", mySocial.logout_div).bind('click', function(e){
                    e.preventDefault();
                    ShowProgress();
                    node.logout(function(){
                        mySocial.status(node);
                    });
                });

                mySocial.render[mySocial.response_type](node, this);
            }

        });
    },

    render : {

        get_albums : function(node, data) {
            var el = mySocial.container;
            $('#images_container').unmask();
            el.html(tmpl(mySocial.albums_tmpl, {list : data}));
            el.find('.file').bind('click', function(){
                ShowProgress();
                var aid = $(this).attr('rel');
                mySocial.breadcrumbs.html('&raquo; ' + $('#album_name', this).html() );
                node.get_images(aid, function(){
                    mySocial.render.get_images(node, this);
                });
            });


            $('.crumbs a').unbind('click').bind('click', function(event){
                event.preventDefault();
            });

            //console.log('albums of', node.name, data);
        },

        get_images : function(node, data) {
            var el = mySocial.container;

            $('#images_container').unmask();
            el.html(tmpl(mySocial.photos_tmpl, {list : data}));
            el.find('.file').bind('click', function(e){

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

            el.find('input', '.file').bind('click', function(e){
                var state = $(this).attr('checked') ? true : false;
                $(this).parent().parent().toggleClass('checked', state);
            });

            $('.crumbs a').bind('click', function(event){
                event.preventDefault();
                ShowProgress();
                mySocial.breadcrumbs.html('');
                mySocial.status(node);
            });

            //console.log('photos', data);
        }
    }
};

var nodeVk = {

    name : 'vk',
    displayName : 'Vkontakte',
    appId : mtoApp.getCfg("vk_id"),
    button : '#vk-login',

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
        VK.Auth.getLoginStatus(function(response) {

            // debug
            //console.info('[getLoginStatus]:', response.status);

            if (response.session && response.status === 'connected') 
            {
                nodeVk.mid = response.mid;
                nodeVk._query = nodeVk[query_type];
                nodeVk._query(function(){
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

    }

};

var nodeFb = {

    name : 'fb',
    displayName : 'Facebook',
    appId : mtoApp.getCfg("fb_id"),
    button : '#fb-login',

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
        FB.getLoginStatus(function(response) {
            if (response.status === 'connected') 
            {
                nodeFb.mid = response.mid;
                nodeFb._query = nodeFb[query_type];
                nodeFb._query(function(){
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
    }

};


function SocialImport()
{
    //$("#image_loader").hide();

    $('#account').attr('checked', true);
    switchImportTab(false, null);

    mySocial.container.find('input:checkbox:checked').each(function(){
        $.ajax({
            url : '/index.php?mode=media&action=processupload',
            data : $.param({
                'import_url' : $(this).val(),
                'folder' : $("#popup_folder").val()
            })
        });
    });

    mySocial.container.entlist('load');
    return false;
}

function ShowProgress()
{
    mySocial.breadcrumbs.html('');
    //mySocial.container.html('<br><br><br><br><br><br>');
    mySocial.container.mask('Загружаем данные...');
}

function switchImportTab(state, node)
{
    if (state) 
    {
        mySocial.container.addClass('social-image-uploader');
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
        mySocial.container.removeClass('social-image-uploader');
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
    }
}