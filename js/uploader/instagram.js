(function(w){
var instagram = function() {

    var application_id, 
        user_token, 
        user_id, 
        oauth_popup,
        oauth_landing, 
        oauth_authorize = 'https://instagram.com/oauth/authorize/',
        api_url = 'https://api.instagram.com/v1/users/',
        cookie = 'cook_instagram',
        events = {};
        apiCall = function(url, callback) {
            $.ajax({
                dataType: "jsonp",
                url: url,
                cache: false,
                success: callback
            });
        },
        eventCall = function(e, args) {
            try {
                for (var i = 0; i < events[e].length; i++) {
                    if (typeof args === "undefined") { args = []; }
                    if (!(args instanceof Array)) {
                        args = [args];
                    }
                    events[e][i].apply(this, args);
                };
            } catch (error) {
                console.log(error);
            };   
        },
        popupParams = function() {
            var w = 700,
                h = 500,
                left = Number((screen.width / 2) - (w / 2)),
                top = Number((screen.height / 2) - (h / 2));
            return 'toolbar=no, status=no, menubar=no, copyhistory=no, width='+w+', height='+h+', top='+top+', left='+left;
        };

    return {
        init: function(conf) {
            application_id = conf.appid;
            oauth_landing = conf.landing;
            if (conf.status) {
                this.getStatus();
            };
        },
        subscribe: function(e, callback) {
            if (!events[e]) {
                events[e] = [];
            };
            events[e].push(callback);
        },
        getStatus: function() {
            if (!$.cookie(cookie)) {
                return false;
            };
            var parts = $.cookie(cookie).split('.');
            user_token = $.cookie(cookie);
            user_id = parts[0];
            return user_token;
        },
        getInfo: function(callback) {
            if (!this.getStatus()) { 
                return;
            };
            apiCall(api_url + user_id + '/?access_token=' + user_token + '&callback=?', callback);
        },
        getRecent: function(url, callback) {
            if (!user_token) {
                return false;
            };
            var url = url || api_url + user_id + '/media/recent/?access_token=' + user_token + '&callback=?';
            apiCall(url, callback);
        },
        login: function() {
            if (!application_id || !oauth_landing) {
                return false;
            };
            var url = oauth_authorize + '?client_id=' + application_id + '&redirect_uri=' + oauth_landing + '&response_type=token';
            oauth_popup = window.open(url, '', popupParams(), false);
            oauth_popup.focus();
        },
        logout: function(callback) {
            user_token = false;
            user_id = false;
            $.cookie(cookie, null);
            if (typeof callback == 'function') {
                callback();
            };
        },
        oauth_callback: function() {
            if (oauth_popup.location.hash) {
                var parts = oauth_popup.location.hash.substr(1).split('=');
                if (parts[0] == 'access_token' && parts[1]) {
                    $.cookie('cook_instagram', parts[1]);
                    this.getStatus();
                    eventCall('login');
                };
            }
        }
    };
}();
w.Instagram = instagram;
})(window);