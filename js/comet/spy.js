$(function(){
	if (navigator.userAgent.toLowerCase().indexOf('chrome') != -1)
        {
		socket = io.connect('http://localhost:8080', {'transports': ['xhr-polling']});
	} 
        else
        {
		socket = io.connect('http://localhost:8080');
	}
	socket.on('connect', function ()
        {
            socket.json.send({event: "register", url: document.location.href, ip: $('#cu_ip').val(), login: $('#cu_login').val(), sid: socket.id});
            socket.on('message', function (msg)
            {
                switch (msg.event)
                {
                    case "write":
			document.querySelector('#log').innerHTML += msg.event + "::"+ unescape(msg.text)+ '<br>';
			document.querySelector('#log').scrollTop = document.querySelector('#log').scrollHeight;
                    break;
                    case "alert":
                        alert(msg.text);
                    break;
                    case "update_list":
                        $('#activity_table tr[rel=row]').remove();
                        for (var i in msg.list)
                        {
                            var row = $('#activity_table #etalon').clone().attr("rel", "row").attr("id", "old_etalon");
                            $("td[rel=login]", row).html($("td[rel=login]", row).html().replace("%login%", msg.list[i].login));
                            $("td[rel=ip]", row).html($("td[rel=ip]", row).html().replace("%ip%", msg.list[i].ip));
                            $("td[rel=id]", row).html($("td[rel=id]", row).html().replace("%id%", msg.list[i].id));
                            $("td[rel=url]", row).html($("td[rel=url]", row).html().replace("%url%", msg.list[i].url));
                            row.appendTo("#activity_table").show();
                        }
                    break;
                }
            });
            if (document.getElementById("activity_table"))
            {
                socket.json.send({event: "list"});
                $("input[rel=alert]").live("keypress", function(e){
                    if (e.which == 13)
                    {
                        var id = $(this).parents("tr:first").find("td[rel=id]").text();
                        socket.json.send({event: "alert", id: id, text: this.value});
                        this.value = "";
                    }
                })
            }
//            document.querySelector('#send').onclick = function()
//            {
//                if (document.querySelector('#input').value.indexOf("alert") >= 0)
//                {
//                    var arr = document.querySelector('#input').value.split(":");
//                    socket.json.send({event: "alert", id: arr[1], text: arr[2]});
//                }
//                else
//                {
//                    if (document.querySelector('#input').value == "list")
//                    {
//                        socket.json.send({event: "list"});
//                    }
//                    else
//                    {
//                        socket.json.send({event: "write", text: escape(document.querySelector('#input').value)});
//                    }
//                }
//                document.querySelector('#input').value = '';
//            };
	});
    
})