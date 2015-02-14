define(["jquery", 
        "module.event_manager"
        ], function($, _em){
    
        
    
    
        _em.bind("all.start", function(){
            $(document).on('change', "select[data-synced], input[data-synced], textarea[data-synced]", function(e){
                sync($(this));
            });
            init();
        });
        
        _em.bind("load.block", function(data){
            //alert('catch');
            init(data['dom']);
        });
        
        function init(p)
        {
            if (typeof(p) == "undefined")
            {
                p = $(document);
            }
            var used = [];
            $("select[data-synced], input[data-synced]:text, input[data-synced]:checked, textarea[data-synced]", p).each(function(){
                if ($.inArray($(this).data('synced'), used) < 0)
                {
                    sync($(this));
                    used.push($(this).data('synced'));
                }
            });
        }
    
    
        function sync(e)
        {
            $("*[data-synced="+e.data("synced")+"]").each(function(){
                if (this !== e[0])
                {
                    switch(this.tagName.toLowerCase())
                    {
                        case 'textarea':
                            $(this).text(get_text(e));
                        break;
                        case 'select':
                            $(this).val(get_val(e));
                        break;
                        case 'input':
                            if ($.inArray($(this).attr('type'), ['checkbox', 'radio']) >= 0)
                            {
                                return;
                            }
                            $(this).val(get_text(e));
                        break;
                        default:
                            if ($(this).data("synced_val"))
                            {
                                $(this).html(get_val(e));
                            }
                            else
                            {
                                $(this).html(get_text(e));
                            }
                        break;
                    }
                }
            })
        }
        
        function get_text(e)
        {
            switch(e[0].tagName.toLowerCase())
            {
                case "select":
                    return $("option:selected", e).text();
                break;
                case "input":
                    if ($.inArray(e.attr('type'), ['radio', 'checkbox']) >= 0)
                    {
                        if (e.prop('checked'))
                        {
                            return e.attr("title");
                        }
                        else
                        {
                            return "";
                        }
                    }
                    else
                    {
                        return e.val();
                    }
                break;
                default:
                    return e.val();
                break;
            }
        }
        
        function get_val(e)
        {
            return e.val();
        }
    
});