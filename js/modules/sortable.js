define(["jquery", 
        "module.event_manager",
        "module.data_proxy"
        ], function($, _em, _proxy){
    
        var _defaults = {
            asc_class: 'sortd',
            desc_class: 'sortu',
            column_class: 'a',
            head_class: 'a'
        };
        var _params = {};
        var _index = 0;
    
        _em.bind("all.start", function(data){
            _params = $.extend(_defaults, data || {});
            $("table[data-sortable]").each(function(){
                init_table($(this));
            });
        });
        _em.bind("sort.init", function(data){
            var scope;
            if (data['elem'])
            {
                scope = data['elem'];
            }
            else
            {
                scope = $("table[data-sortable]");
            }
            scope.each(function(){
                init_table($(this));
            });
        });
        _em.bind("sort.repaint", function(data){
            var scope;
            if (data['elem'])
            {
                scope = data['elem'];
            }
            else
            {
                scope = $("table[data-sortable]");
            }
            if (data['elem'] && data['order_by'])
            {
                var sort = data['order_by'].split(' ');
                $("thread th[data-sort_field]", data['elem']).data("sort", "click");
                $("thead th[data-sort_field="+sort[0]+"]").data("sort", sort[1]);
            }
            scope.each(function(){
                var t = $(this);
                $("thead th", t).each(function(){
                    var th = $(this);
                    if (th.data("sort"))
                    {
                        if ($.inArray(th.data("sort"), ["asc", "desc"]) >= 0)
                        {
                            sort_repaint(t, th);
                        }
                    }
                });
            });
        });
        
    
    
    function init_table(t)
    {
        $("thead th", t).each(function(){
            var th = $(this);
            if (th.data("sort"))
            {
                if ($.inArray(th.data("sort"), ["asc", "desc"]) >= 0 && t.data("sortable") == "client")
                {
                    sort_table_client(t, th);
                }
                $("a", th).on("click", function(e){
                    e.preventDefault();
                    var h = $(this).parents("th:first");
                    var t = th.parents("table:first");
                    var order = h.data('sort') == "asc" ? "desc" : "asc";
                    h.parents("tr:first").find("th").data("sort", "click");
                    h.data("sort", order);
                    if (t.data("sortable") == "client")
                    {
                        sort_table_client(t, h);
                        sort_repaint(t, h);
                    }
                    if (t.data("sortable") == "server")
                    {
                        _em.trigger("sort.table", {elem: t, field: h.data("sort_field")+" "+order});
                    }
                });
            }
        });
    }
    

    function  sort_repaint(t, h)
    {
        h.parents("tr:first").find("th").removeClass(_params['asc_class']).removeClass(_params['desc_class']).removeClass(_params['head_class']);
        h.addClass(_params[h.data('sort')+"_class"])/*.addClass(_params['head_class'])*/;
        $("tr[data-groupby_row]", t).remove();
        if (h.data("groupby"))
        {
            sort_groupby(t, h);
        }
//        $("tbody td", t).removeClass(_params['column_class']);
//        $("tbody tr", t).each(function(){
//            var e = $(this);
//            $("td", e).eq(h.index()).addClass(_params['column_class']);
//        });
        
    }
    
    function sort_groupby($table, $th)
    {
        var colspan = $th.parent().find("th").length;
        var index = 0;
        var sum = [];
        var prefix = $th.data("groupby_prefix");
        var c = 0;
        var cr = $("tbody tr:first", $table);
        var cg = $("tbody tr:first td", $table).eq($th.index()).text();
        if (prefix)
        {
            cg = cg.substr(0, prefix);
        }
        
        $("th", $th.parent()).each(function(){
            sum.push(0);
        });

        var rf = function(nr){
            var html = $table.data("groupby_tpl");
            html = html.replace("%col", cg);
            html = html.replace("%c", c);
            html = html.replace(/%sum\((\d+)\)/g, function(s, n){
                return sum[parseInt(n)];
            });
            $('<tr class="info" data-groupby_row="1"><td colspan="'+colspan+'">' + html + '</td></tr>').insertBefore(cr);
            c = 0;
            cr = nr;
            if (nr)
            {
                cg = $("td", nr).eq($th.index()).text();
                if (prefix)
                {
                    cg = cg.substr(0, prefix);
                }
            }
            for (i=0; i<sum.length; i++)
            {
                sum[i] = 0;
            }
        };
        
        $("tbody tr", $table).each(function(){
            var r = $(this);
            var i;
            var val;
            var fval;
            var rg = $("td", r).eq($th.index()).text();
            if (prefix)
            {
                rg = rg.substr(0, prefix);
            }
            if (cg != rg)
            {
                rf(r);
            }
            c++;
            for (i=0; i<sum.length; i++)
            {
                val = $("td", r).eq(i).text();
                fval = parseFloat(val.replace(/[^0-9.]/g, ""));
                if (!isNaN(fval))
                {
                    sum[i] += fval;
                }
            }
        });
        if (c > 0)
        {
            rf(null);
        }
    }
    
    function sort_table_client($table, $th)
    {
        var rows = [];
        $("tbody tr", $table).each(function(){
            //if (!$(this).data("grid_head"))
            //{
                rows.push($(this).detach());
            //}
        });
        //_index = $th.data("sort_index");
        _index = $th.index();
        var method = $th.data("sort_method");
        if (!method)
        {
            method = "native";
        }
        rows.sort(sort_method($th.data("sort"), method));
        for (var i=0; i<rows.length; i++)
        {
            rows[i].appendTo($("tbody", $table));
        }
    }
    
    function sort_method(order, method)
    {
        switch (method)
        {
            case "native":
                if (order == "desc")
                {
                    return function(a,b){var x = cmp_vals([a, b]); return cmp_desc(x[0], x[1])};
                }
                else
                {
                    return function(a, b){var x = cmp_vals([a, b]); return cmp_asc(x[0], x[1])};
                }
            break;
            case "int":
                if (order == "desc")
                {
                    return function(a, b){var x = cmp_int(cmp_vals([a, b])); return cmp_desc(x[0], x[1])};
                }
                else
                {
                    return function(a, b){var x = cmp_int(cmp_vals([a, b])); return cmp_asc(x[0], x[1])};
                }
            break;
            case "notags":
                if (order == "desc")
                {
                    return function(a,b){var x = cmp_notags([a, b]); return cmp_desc(x[0], x[1])};
                }
                else
                {
                    return function(a, b){var x = cmp_notags([a, b]); return cmp_asc(x[0], x[1])};
                }
            break;
        }
    }
    
    function cmp_int(items)
    {
        var res = [];
        for (var i=0; i<items.length; i++)
        {
            res.push(parseInt(items[i]));
        }
        return res;
    }
    
    function cmp_vals(items)
    {
        var res = [];
        for (var i=0; i<items.length; i++)
        {
            res.push($("td", items[i]).eq(_index).html());
        }
        return res;
    }
    
    function cmp_notags(items)
    {
        var res = [];
        for (var i=0; i<items.length; i++)
        {
            res.push($("td", items[i]).eq(_index).html().replace(/<\/?[^>]+>/gi, ''));
        }
        return res;
    }
    
    function cmp_asc(a, b)
    {
        if (a > b)
        {
            return 1;
        }
        else if (a < b)
        {
            return -1;
        }
        else
        {
            return 0;
        }
    }
    
    function cmp_desc(a, b)
    {
        if (a > b)
        {
            return -1;
        }
        else if (a < b)
        {
            return 1;
        }
        else
        {
            return 0;
        }
    }
    
    return {
                
    };
    
    
    
    
});