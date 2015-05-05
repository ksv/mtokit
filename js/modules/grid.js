define([
    "cfg",
    "jquery",
    "module.event_manager",
    "module.toolkit",
    "module.data_proxy",
    "module.sortable",
    "module.scrollable",
    "plugin.loadmask"
], function(_cfg, $, _em, _tools, _proxy) {

    var grids = {};
    var _index = 0;
    var mass_opened = false;
    var $mass_panel;
    var con = console;

    var _impl = {
    };
    
    
    
    
    
    _em.impl(_impl);

    _em.bind("all.start", function(data) {
        $mass_panel = $('#hlp_blck_bot');
        $("*[data-grid]").each(function(){
            create_grid($(this));
        });
    });
    _em.bind("grid.start", function(data){
        if (!data['cont'])
        {
            return;
        }
        $("*[data-grid]", data['cont']).each(function(){
            if (!$(this).data("grid_started"))
            {
                create_grid($(this));
            }
        });
    });
    _em.bind("filters.apply", function(data){
        $("*[data-grid]").each(function(){
            var e = $(this);
            grids[e.data("grid_id")].options['page'] = 1;
            update_grid(e.data("grid_id"), {}, true, true);
            $("input[data-chk_all]", e).prop("checked", false);
        });
    });
    _em.bind("grid.hide_mass_panel", function(data){
        if (mass_opened)
        {
            $mass_panel.hide();
        }
    });
    _em.bind("grid.show_mass_panel", function(e){
        if (mass_opened)
        {
            $mass_panel.show();
        }
    });
    
    _em.bind("grid.render_row", function(data){
        var row = null;
        if (typeof(data['elem']) != "undefined")
        {
            row = data['elem'].parents("tr[data-row_id]:first");
            delete data['elem'];
        }
        if (typeof(data['row']) != "undefined")
        {
            row = data['row'];
            delete data['row'];
        }
        if (typeof(data['gridref']) != "undefined")
        {
            if (data['gridref'].indexOf(':') > 0)
            {
                var refs = data['gridref'].split(':');
                row = $('*[data-grid_id='+refs[0]+']').find('tr[data-row_id='+refs[1]+']');
            }
        }
        if (typeof(data['append']) != "undefined")
        {
            if (grids[data['append']])
            {
                row = $("<tr>").insertAfter($("tr[data-row_id]:last", grids[data['append']]['target']));
            }
        }
        if (typeof(data['prepend']) != "undefined")
        {
            if (grids[data['prepend']])
            {
                row = $("<tr>").insertBefore($("tr[data-row_id]:first", grids[data['prepend']]['target']));
            }
        }
        if (row)
        {
            render_row(row, data);
        }
        else
        {
            alert("Unable to create row");
        }
    });
    _em.bind("grid.reload", function(data){
        delete data['evt'];
        update_grid(data['grid_id'], data, true, true);
    });
    
    _em.bind("sort.table", function(data){
        if (!data['elem'])
        {
            return;
        }
        var id = data['elem'].data("grid_id");
        var order = data['field'];
        update_grid(id, {set_order: order, page: 1}, true, true);
    });


    return {
        getAggregate: function(id, column, fun)
        {
            var c = 0;
            var s = 0;
            $("tr[data-row_id]", grids[id].target).each(function(){
                var col = $("td", $(this)).eq(column);
                c++;
                s += parseInt(col.text());
            });
            switch (fun)
            {
                case "count":
                    return c;
                break;
                case "sum":
                    return s;
                break;
                case "avg":
                    return s/c;
                break;
            }
        },
        getGroupBy: function(id, gcolumn, acolumn)
        {
            //alert('aa');
            var cg = $("tr[data-row_id]:first", grids[id].target).find("td").eq(gcolumn).text();
            var cc = 0;
            var cs = 0;
            var data = [];
            $("tr[data-row_id]", grids[id].target).each(function(){
                var acol = $("td", $(this)).eq(acolumn);
                var gcol = $("td", $(this)).eq(gcolumn);
                if (cg != gcol.text())
                {
                    if (cc != 0)
                    {
                        data.push({g: cg, cnt: cc, sm: cs, av: cs/cc});
                        cg = gcol.text();
                        cc=0;
                        cs=0;
                    }
                }
                cc++;
                cs+=parseInt(acol.text());
            })
            if (cc > 0)
            {
                data.push({g: cg, cnt: cc, sm: cs, av: cs/cc});
            }
            return data;
        }
        
    };
    
    
    function create_grid(t)
    {
        var id = t.data("grid_id");
        if (!id)
        {
            id = "grid_" + (++_index);
            t.data("grid_id", id).attr("data-grid_id", id);
        }
        var options = _tools.unserialize(t.data("options")) || {};
        var reload = $("*[data-grid_ctl_reload="+id+"]");
        var pager_type = $("*[data-grid_ctl_pager_type="+id+"]");
        var pager = $("*[data-grid_ctl_pager="+id+"]");
        var pages = $("*[data-grid_ctl_pages="+id+"]");
        var total = $("*[data-grid_ctl_total="+id+"]");
        var mass = $("*[data-grid_ctl_mass="+id+"]");
        
        
        options['has_panel'] = false;
        options['has_pager'] = false;
        options['has_infinity'] = false;
        options['page'] = 1;
        options['pages'] = 0;
        options['records'] = 0;
        options['infinity_mode'] = false;
        options['per_page_options'] = [];
        options['default_per_page'] = 0;
        options['sort_order'] = "";
        options['default_order'] = "";
        if ($('#sph_search_keyword').length)
        {
            options['sph_search_keyword'] = $('#sph_search_keyword').val();
        }
        if (options['per_page'])
        {
            var pp_parts = options['per_page'].split(",");
            var ohtml = ["<option value=''></option>"];
            for (var i=0; i<pp_parts.length; i++)
            {
                
                if (pp_parts[i] == '-1')
                {
                    if (pager_type.length)
                    {
                        pager_type.show();
                        $("li a", pager_type).on("click", function(e){
                            e.preventDefault();
                            var p = $(this).parent();
                            var gid = p.parents("*[grid_ctl_pager_type]").data("grid_ctl_pager_type");
                            var per_page = 0;
                            if (p.data("ref") == "pager")
                            {
                                grids[gid]['options'].infinity_mode = false;
                                grids[gid]['options'].page = 1;
                                per_page = $("select option", grids[gid].pager).eq(2).attr("value");
                            }
                            if (p.data("ref") == "infinity")
                            {
                                grids[gid]['options'].infinity_mode = true;
                                per_page =-1;
                            }
                            if (per_page != 0)
                            {
                                update_grid(gid, {set_per_page: per_page}, true, true);
                            }
                        });
                    }
                    options['has_infinity'] = true;
                }                
                else
                {
                    
                    var s = "";
                    if (options['default_per_page'] == 0)
                    {
                        options['default_per_page'] = pp_parts[i];
                        s = "selected";
                    }
                 
                    if (pp_parts[i] == 'all')
                    {    
                        ohtml.push("<option value='"+pp_parts[i]+"' >Все</option>");
                    } else
                    {                    
                        ohtml.push("<option value='"+pp_parts[i]+"' "+s+">" + pp_parts[i] + "</option>");
                    }    
                }
            }
            if (ohtml.length > 1)
            {
                options['per_page_options'] = ohtml;
                if (pages.length)
                {
                    pages.on("click", "*[data-ref=next]", function(e){
                        e.preventDefault();
                        var gid = $(this).parents("*[data-grid_ctl_pages]:first").data("grid_ctl_pages");
                        var options = grids[gid].options;
                        if (options['pages'] > 1 && options['page'] < options['pages'])
                        {
                            grids[gid].options['page']++;
                            update_grid(gid, {}, false, true);
                        }
                    });
                    pages.on("click", "*[data-ref=prev]", function(e){
                        e.preventDefault();
                        var gid = $(this).parents("*[data-grid_ctl_pages]").data("grid_ctl_pages");
                        var options = grids[gid].options;
                        if (options['page'] > 1 )
                        {
                            grids[gid].options['page']--;
                            update_grid(gid, {}, false, true);
                        }
                    });
                    pages.on("click", "*[data-ref=page]", function(e){
                        e.preventDefault();
                        var gid = $(this).parents("*[data-grid_ctl_pages]:first").data("grid_ctl_pages");
                        var options = grids[gid].options;
                        var p = $(this).data("page")
                        if (p >= 1 && p <= options['pages'])
                        {
                            grids[gid].options['page'] = p;
                            update_grid(gid, {}, false, true);
                        }
                    });
                }
                if (pager.length)
                {
                    $("select", pager).html(ohtml.join(""));
                    $("select", pager).change(function(e){
                        var gid = $(this).parents("*[data-grid_ctl_pager]:first").data("grid_ctl_pager");
                        grids[gid].options['page'] = 1;
                        update_grid(gid, {set_per_page : $(this).val()}, true, true);
                    });
                    _tools.init_combos(pager, {width: '70px'});
                }
            }
        }
        if (reload.length)
        {
            reload.on("click", function(e){
                e.preventDefault();
                update_grid($(this).data("grid_ctl_reload"), {}, true, true);
            });
        }
        $("thead th[data-sort]", t).each(function(){
            var h = $(this);
            if ($.inArray(h.data("sort"), ["asc", "desc"]) >= 0)
            {
                options['default_order'] = h.data("sort_field") + " " + h.data("sort");
            }
        });
        grids[id] = {target: t, options: options, reload: reload, pager: pager, pages: pages, pager_type: pager_type, total: total, mass: mass};
        _em.trigger("scroll.init", {elem: t});
        update_grid(id, {}, true, true);
        if (mass.length)
        {
            init_mass_panel(t, mass);
        }
        if (t.data("grid_autoclick"))
        {
            t.on("click", function(e){
                if ($(e.target).prop("tagName").toLowerCase() == "td")
                {
                    var td = $(e.target);
                    if ($(td).parents("tr:first").data("row_id"))
                    {
                        $("a:first span", $(td).parents("tr:first")).click();
                    }
                }
            })
        }
        
    }
    
    
    function update_grid(id, args, panel, data)
    {
        args = args || {};
        var params = grids[id].options;
        for (var i in args)
        {
            params[i] = args[i];
        }
        if (panel && grids[id].pager.length)
        {
            grids[id].target.parent().mask("Загрузка...");
            _proxy.call(grids[id].target.data("grid"), params, function(result){
                for (var i in result)
                {
                    grids[id].options[i] = result[i];
                }
                update_panel(id);
                if (data)
                {
                    render_grid_rows(id, args);
                }
            });
        }
        else
        {
            update_panel(id);
            if (data)
            {
                grids[id].target.parent().mask("Загрузка...");
                render_grid_rows(id, args);
            }
        }
    }
    
    function update_panel(id)
    {
        if (grids[id].pager.length <= 0)
        {
            return;
        }
        var options = grids[id].options;
       // console.log('SSS');
       // console.log(options);
        if (options['per_page'] == -1)
        {
            options['infinity_mode'] = 1;
        }
        if (options['infinity_mode'])
        {
            $("li", grids[id].pager_type).removeClass("a");
            $("li[data-ref=infinity]", grids[id].pager_type).addClass("a");
            //grids[id].pager.hide();
            grids[id].pages.hide();
        }
        else
        {
            $("li", grids[id].pager_type).removeClass("a");
            $("li[data-ref=pager]", grids[id].pager_type).addClass("a");
            if (options['per_page'])
            {
                grids[id].pager.show();
                grids[id].pages.show();
                _tools.init_combos(grids[id].pager);
            }
            $("select", grids[id].pager).val(options['per_page']).trigger("chosen:updated");
        }
        $("*[data-ref=curpage]", grids[id].pages).html("Страница " + options['page'] + ' из ' +options['pages']);
        $(grids[id].total).html(options['records']);
        var arrs = $("*[data-ref=arrows]", grids[id].pages);
        if (arrs.length)
        {
            var html = [];
            if (options['page'] > 1)
            {
                html.push('<a href="" class="arr larr"><i data-ref="prev"></i></a>');
            }
            else
            {
                html.push('<div class="arr larr"><i data-ref="prev"></i></div>');
            }
            if (options['pages'] > 1 && options['page'] < options['pages'])
            {
                html.push('<a href="" class="arr rarr"><i data-ref="next"></i></a>');
            }
            else
            {
                html.push('<div class="arr rarr"><i data-ref="next"></i></div>');
            }
            arrs.html(html.join(""));
        }
        var pg = $("*[data-ref=bspg]", grids[id].pages);
        if (pg.length)
        {
            var html = [];
            html.push('<li class='+(options['page'] > 1 ? "" : "disabled")+'><a href="#" data-ref="prev">&laquo;</a></li>');
            for (var i=1; i<=(options['pages'] > 10 ? 10 : options['pages']); i++)
            {
                html.push('<li class="'+(i==options['page'] ? "active" : "")+'"><a href="#" data-ref="page" data-page="'+i+'">'+i+'</a></li>');
            }
            html.push('<li class="'+(options['pages'] > 1 && options['page'] < options['pages'] ? "" : "disabled")+'"><a href="#" data-ref="next">&raquo;</a></li>');
            $("ul", pg).html(html.join(""));
        }
    }
    
    function render_grid_rows(id, args)
    {
        args = args || {};
        var grid = grids[id].target;
        //$("tr", grid).not("*[data-grid_head]").remove();
        //$("tbody", grid).html("");
        _proxy.render(grid.data("grid"), grids[id].options, function(html){
            //$(html.trim()).insertAfter($("tr[data-grid_head]", grid));
            grid.parent().unmask();
            var body = $("*[data-grid_body="+id+"]", grid);
            if (body.length)
            {
                body.html(html);
            }
            else
            {
                $("tbody", grid).html(html);
            }
            _em.trigger("grid.loaded", {grid: id});
            _em.trigger("sort.repaint", {elem: grids[id].target, order_by: grids[id].options['order'] ? grids[id].options['order'] : ''});
            var s = typeof(args['grid_autopos']) != "undefined" ? "save" : "top";
            _em.trigger("scroll.update", {elem: grids[id].target, scrollTo: s});
        });
    }
    
    function init_mass_panel(p, mass)
    {
        p.on("click", function(e){
            var t = $(e.target);
            if (t.is(":checkbox"))
            {
                if (t.data("chk") || t.data("chk_all"))
                {
                    var l = t.data("chk") ? t.data("chk") : t.data("chk_all");
                    if (t.data("chk_all"))
                    {
                        $(":checkbox[data-chk="+l+"]", p).prop("checked", t.prop("checked"));
                    }
                    if ($("input[data-chk="+l+"]:checked").length > 0)
                    {
                        if ($mass_panel.not(":visible"))
                        {
                            $mass_panel.show();
                            mass_opened = true;
                            _tools.init_combos($mass_panel, {top: true});
                            _em.trigger("scroll.update");
//                            b.fadeIn(300, function(){
//                                _tools.init_combos(b, {top: true});
//                                b.show();
//                                _em.trigger("scroll.update");
//                            });
                        }
                    }
                    else
                    {
                        if ($mass_panel.is(":visible"))
                        {
                            $mass_panel.hide();
                            mass_opened = false;
                            _em.trigger("scroll.update");
//                            b.fadeOut(300, function(){
//                                b.hide();
//                                _em.trigger("scroll.update");
//                            });
                        }
                    }
                }
            }
            $(".lnk_cancel", $mass_panel).on("click", function(e){
                e.preventDefault();
                $mass_panel.hide();
                mass_opened = false;
                _em.trigger("scroll.update");
//                b.fadeOut(300, function(){
//                                b.hide();
//                                _em.trigger("scroll.update");
//                });
            });
            _tools.init_datepicker($("input.inl_date", mass));
            $("select[name=action]", mass).on("change", function(){
                $(".hlp_optns", mass).hide();
                $(this).parents(".fld").removeClass("lst");
                $(".hlp_optn_"+$(this).val()).show(10, function(){
                    _tools.init_combos(mass, {top: true});
                      //$("select.hlp_fakeit:visible", blck).chosen({no_results_text: "Ничего не найдено"});
                });
            });
        });
    }
    
    
//    function init_table(t)
//    {
//        var options = _tools.unserialize(t.data("options"));
//        if (options['selector'])
//        {
//            _tools.init_checkboxes(t);
//        }
//    }
    
//    function render_table(t, args)
//    {
//        args = args || {};
//        if (t.data("grid") == "client")
//        {
//            init_table(t);
//            return;
//        }
//        $("tr", t).not("*[data-grid_head]").remove();
//        _proxy.render(t.data("grid"), args, function(html){
//            $(html.trim()).insertAfter($("tr[data-grid_head]", t));
//            init_table(t);
//            _em.trigger("grid.loaded");
//        });
//    }
    
    function render_row(row, args)
    {
        args = args || {};
        var grid = row.parents("*[data-grid]:first");
        if (grid.length <= 0)
        {
            return;
        }
        if (row.data("row_id"))
        {
            args['filter_rowid'] = row.data("row_id");
        }
        delete args['evt'];
        _proxy.render(grid.data("grid"), args, function(html){
            var dom = $(html.trim());
            if (dom.data("row_id"))
            {
                row.replaceWith(dom);
                var eargs = {elem: grid};
                if (args['append'])
                {
                    eargs['scrollTo'] = "bottom";
                }
                if (args['prepend'])
                {
                    eargs['scrollTo'] = "top";
                }
                if (args['rowclick'])
                {
                    $("a:first span", $("tr[data-row_id="+dom.data('row_id')+"]", grid)).click();
                }
                _em.trigger("scroll.update", eargs);
            }
            else
            {
                row.remove();
            }
        });
    }




});