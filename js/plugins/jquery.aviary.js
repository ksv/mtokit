/**
 * @description Aviary Feather editor
 *              usage: $(image).aviary(callback);
 */
(function ($, Aviary, config) {

    /**
     * Preventing multiply plugin instances
     */
    if ($.fn.aviary) {
        return;
    };

    /**
     * Storing Feather instance outside jQuery
     */
    var instance = new Aviary.Feather (config);

    /**
     * Attaching editor to be a jQuery plugin
     */
    $.fn.aviary = function (callback) {

        var img_id  = $(this).attr('id'),
            data = {},

            reloadPage = function () {
                if (confirm('Произошла ошибка. Перезагрузить страницу?')) { window.location.reload(); };
            },

            isOpened = function () {
                return $("#avpw_font_popup").length > 0;
            },

            onError = function (e) {
                mtoApp.getCfg('debug') ? console.log(e.message) : reloadPage();
            },

            onSave = function (id, newURL) {
                $.post(mtoApp.getCfg('upload_url'), {import_url: newURL}, function (json) {
                    data = $.parseJSON (json);
                    if (isOpened() === false) {
                        callback(data);
                    };
                });
            },

            onClose = function () {
                if ($.isEmptyObject(data) == false) {
                    callback(data);
                };
            },

            launch = function (url) {
                instance.launch({
                    url: url,
                    image: img_id, 
                    onSave: onSave,
                    onClose: onClose,
                    onError: onError
                });
            };

        // fetching image url and handling to editor
        $.post('/media/fullsizeurl?media_id=' + img_id, launch);
    };

}) (jQuery, Aviary, mtoApp.getCfg('aviary') || {});
