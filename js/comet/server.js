
var util = require('util');
var memcache = require('memcache');
var mcConnected = false;


var clientManager = new function()
{
    var clients = {};
    var locks = {};
    
    this.connect = function(client)
    {
        clients[client.id] = client;
        util.log("("+this.size()+") "+client.id+" connected");
    }

    this.disconnect = function(id)
    {
        delete clients[id];
        for (var i in locks)
        {
            if (locks[i] == id)
            {
                delete locks[i];
                util.log("Lock <"+i+"> released");
            }
        }
        util.log("("+this.size()+") "+id+" disconnected");
    }
    
    this.lock_info = function(name)
    {
        var info = {};
        var c = this.get(locks[name]);
        info.login = c.login;
        return info;
    }
    
    this.get_lock = function(id, name)
    {
        if (typeof(locks[name]) == "undefined")
        {
            locks[name] = id;
            return {type: 'success', info: this.lock_info(name)};
        }
        else
        {
            return {type: 'fail', info: this.lock_info(name)};
        }
    }
    
    this.remove_lock = function(id, name)
    {
        if (locks[name] == id)
        {
            delete locks[name];
            util.log("Lock <" + name + "> released");
            return true;
        }
        return false;
    }

    this.update = function(id, data)
    {
        for (var i in data)
        {
            if (i != "act" && i != "sid")
            {
                clients[id][i] = data[i];
            }
        }
    }

    this.get = function(id)
    {
        return clients[id];
    }

    this.getByUserId = function(user_id)
    {
        for (var i in clients)
        {
            //if (clients[i].user_id == )
        }
    }

    this.size = function()
    {
        var c = 0;
        for (var i in clients)
        {
            c++;
        }
        return c;
    }

    this.list = function()
    {
        var res = {};
        for (var i in clients)
        {
            res[i] = {};
            for (var j in clients[i])
            {
                if (j != "socket")
                {
                    res[i][j] = clients[i][j];
                }
            }
        }
        return res;
    }

    this.parseQueueStr = function(qstr)
    {
        var queue = [];
        var parts = qstr.split("|");
        for (var i=0; i<parts.length; i++)
        {
            var items = parts[i].split(":");
            queue.push({user_id: items[0], item_type: items[1], task_date: items[2], delivery_date: items[3], event_type: items[4], order_num: items[5], items: items[6], sides: items[7]});
        }
        return queue;
    }

    this.notifyTasklist = function(qstr)
    {
        var queue = this.parseQueueStr(qstr);
        for (var i=0; i<queue.length; i++)
        {
            this.notifyTasklistClient(queue[i]);
        }
    }

    this.notifyTasklistClient = function(item)
    {
        for (var i in clients)
        {
            if (item['event_type'] == "sync")
            {
                if (clients[i].user_id == item.user_id)
                {
                    item['event'] = "TaskNotify";
                    clients[i].socket.json.send(item);
                    util.log('Client ' + clients[i].id + " synced by tasklist["+clients[i].user_id+"]");
                }
            }
            else
            {
                if (clients[i].use_tasklist)
                {
                    var ctypes = clients[i].task_item_types.split(",");
                    for (var j=0; j<ctypes.length; j++)
                    {
                        if (parseInt(item.item_type) == parseInt(ctypes[j]))
                        {
                            item['event'] = "TaskNotify";
                            clients[i].socket.json.send(item);
                            util.log("Client " + clients[i].id + " notified by tasklist["+clients[i].user_id+"]");
                            break;
                        }
                    }
                }
            }
        }
    }

    this.getAll = function()
    {
        return clients;
    }

}

var io = require('socket.io').listen(8080);

process.on('uncaughtException', function(e){
    util.log('Exception: '+e);
});

var mc = new memcache.Client(11211, '127.0.0.1');
mc.on("connect", function(){
    util.log("Connected to memcache");
    mcConnected = true;
});
mc.connect();

io.set('log level', 0);
io.sockets.on('connection', function (socket)
{
        var time = (new Date).toLocaleTimeString();
	socket.json.send({'event': 'Connected', 'name': socket.id});
        clientManager.connect({id: socket.id, socket: socket, start_ts: time, ip: socket.handshake.address.address});
	socket.on('message', function (msg)
        {
            switch (msg.event)
            {
                case "alert":
                    util.log("ALERT");
                    clientManager.get(msg.id).socket.json.send({event: "Alert", text: msg.text});
                break;
                case "tasklist_notified":
                    util.log(socket.id+" receive tasklist notifikation["+clientManager.get(socket.id).user_id+"]");
                    socket.json.send({event: "Log", text: "XXX"});
                break;
                case "diff_alert":
                    util.log("DIFF ALERT RECEIVED FOR: "+msg.id);
                    clientManager.get(msg.id).socket.json.send({event: "DiffAlert", text: msg.text+":diff"});
                break;
                case "register":
                    util.log("REGISTER");
                    msg['ts'] = (new Date).toLocaleTimeString();
                    clientManager.update(socket.id, msg);
                    socket.json.send({event: "Registered"});
                break;
                case "update":
                    util.log("UPDATE");
                    msg['ts'] = (new Date).toLocaleTimeString();
                    clientManager.update(socket.id, msg);
                    socket.json.send({event: "Updated"});
                break;
                case "throw":
                    throw new Error(msg.text);
                break;
                case "reload_js":
                    util.log("RELOAD JS RECEIVED FOR: "+msg.id);
                    clientManager.get(msg.id).socket.json.send({event: "ReloadScript", path: msg.path});
                break;
                case "reload_all_js":
                    util.log("RELOAD ALL JS RECEIVED");
                    var clist = clientManager.getAll();
                    for (var i in clist)
                    {
                        clist[i].socket.json.send({event: 'ReloadScript', path: msg.path});
                    }
                    //socket.broadcast.json.send({event: "ReloadScript", path: msg.path});
                break;
                case "logo_animate":
                    util.log("LOGO ANIMATE");
                    clientManager.get(msg.id).socket.json.send({event: "LogoAnimate"});
                break;
                case "exit":
                    util.log("Shutdown");
                    process.exit(0);
                break;
                case "list":
                    socket.json.send({event: "UpdateList", list: clientManager.list()});
                break;
                case "get_lock":
                    util.log("GET LOCK TRY FOR <"+msg.name+">");
                    var l = clientManager.get_lock(socket.id, msg.name);
                    if (l.type == "success")
                    {
                        util.log("GET LOCK <"+msg.name+">");
                    }
                    else
                    {
                        util.log("GET LOCK FAILED <"+msg.name+">");
                    }
                    socket.json.send({event: "GetLock", type: l.type, info: l.info});
                break;
                case "remove_lock":
                    util.log("REMOVE LOCK TRY: " + msg.name);
                    var res = clientManager.remove_lock(socket.id, msg.name);
                    var m = res ? "Lock removed" : "Lock not found";
                    socket.json.send({event: "RemoveLock", res: m});
                break;
                case "pong":
                    clientManager.update(socket.id, {ts: (new Date).toLocaleTimeString()});
                break;
                default:
                    var time = (new Date).toLocaleTimeString();
                    socket.json.send({'event': 'Write', 'name': ID, 'text': msg.message, 'time': time});
                    socket.broadcast.json.send({'event': 'Write', 'name': ID, 'text': msg.message, 'time': time})
                break;
            }
	});
	socket.on('disconnect', function()
        {
            util.log("DISCONNECT");
            clientManager.disconnect(socket.id)
	});
});

setInterval(function(){
    var clients = clientManager.getAll();
    for (var i in clients)
    {
        clients[i].socket.json.send({event: 'Ping'});
        util.log(i+" pinged");
    }
}, 180000);
setInterval(function(){
    if (mcConnected)
    {
        mc.get("tasklist_lock", function(error, result){
            if (!error && !result)
            {
                mc.get("tasklist_queue", function(error, result){
                    if (result)
                    {
                        util.log("QUEUE: received "+result);
                        mc.delete("tasklist_queue", function(error, result){
                           util.log("QUEUE: cleaned");
                        });
                        clientManager.notifyTasklist(result);
                    }
                });
            }
            else
            {
                util.log("QUEUE: locked");
            }
//            if (error)
//            {
//                util.log("memcache error:"+ error);
//            }
//            else if (result)
//            {
//                util.log("memcache result:"+result);
//            }
//            else
//            {
//                util.log("unknown memcache event. error:"+error+", result:"+result);
//            }
        });
    }
    else
    {
        util.log("Not connected to memcache");
    }
}, 5000);
