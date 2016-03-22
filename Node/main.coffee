# 第三方模块
express    = require('express')
app        = express()
server     = require('http').createServer(app)
io         = require('socket.io').listen(server)
bodyParser = require('body-parser')
request    = require('request')
moment     = require('moment')
wechatLib  = require('wechat-enterprise')

# 自定义模块
config     = require(__dirname + '/config')
wechat     = require(__dirname + '/wechat')
wechatApi  = require(__dirname + '/wechatApi')

# log4js日志
logger = config.logger

# 处理JSON
app.use bodyParser.json
    limit: '1mb'
app.use bodyParser.urlencoded
    extended: true

# 微信企业号回调
app.use '/wechat/callback', wechatLib config.wechat, wechat

# 企业号API
app.all "/wechat/:apiName", wechatApi.route

# 发送Socket消息
app.all '/socket/:room', (req, res) ->
    room = req.params.room
    for event, data of req.body
        logger.info "向房间#{room}发送数据"
        logger.debug "数据内容为：", data
        io.to(room).emit(event, data)
    res.send('socket.io')

# Socket
io.on 'connection', (socket) ->
    # Post请求函数
    post = (url, token, params, callback) ->
        params.token = token
        options =
            url : config.inUrl + url
            form: params
        request.post options, (error, response, body) ->
            try
                result = JSON.parse body
            catch error
                logger.error "JSON解析失败：", error
                logger.error "JSON如下：", body
                return callback "JSON解析失败"
            if parseInt(result.code) isnt 0
                return callback true
            callback null, result.info, result.data

    # 推送在线用户列表
    sendOnlineUser = ->
        onlineUser = {}
        for id, tmpSocket of io.sockets.connected
            user = tmpSocket.user
            if !user then continue
            if user.sid of onlineUser
                onlineUser[user.sid].count += 1
                continue
            onlineUser[user.sid] =
                'sid'   : user.sid
                'name'  : user.name
                'rank'  : user.rank
                'count' : 1
                'time'  : user.time
        onlineUser = (user for sid, user of onlineUser)
        logger.debug "在线用户列表为：", onlineUser
        io.to('admin').emit 'online', onlineUser
        return onlineUser

    # 获取推送内容
    fetchMessage = ->
        if !socket.user then return
        post '/push/fetch', socket.user.token, {}, (error, info, data) ->
            if error then return
            io.to(data.uid).emit 'push', data

    # 获取用户Token及个人信息
    socket.on 'token', (token) ->
        if !token then return
        logger.info "收到用户提交的TOKEN"
        logger.debug "TOKEN值为：#{token}"
        post '/user', token, {}, (error, user) ->
            if error then return
            user.token = token
            socket.user = user
            socket.join user.sid
            if user.isAdmin then socket.join 'admin'
            fetchMessage()
            sendOnlineUser()

    # 推送消息
    socket.on 'push', (message) ->
        # 判断管理员
        if !socket.user?.isAdmin then return
        # 判断数据类型是否为对象
        if typeof message isnt 'object' then return
        # 发送消息
        message.title = "来自管理员 #{socket.user.name} 的消息："
        post '/push/add', socket.user.token, message, (error, info, data) ->
            if error then return socket.emit 'push',
                type   : 0
                title  : '系统提示：'
                content: '消息发送失败，请检查账号是否已经离线'
                time   : moment().format('YYYY-MM-DD hh:mm:ss')
            io.to(data.uid).emit 'push', data

    # 接收到推送
    socket.on 'achieve', (id) ->
        post '/push/achieve', socket.user.token, id:id, (error) ->
            if error then return
            fetchMessage()

    # 弹幕
    socket.on 'barrage', (data) ->
        logger.info '弹幕', data
        io.emit 'barrage', (data)

    # 推送实时日志
    socket.on 'log', ->
        if socket.user?.isAdmin then socket.join 'log'

    # 获取在线用户
    socket.on 'online', ->
        if socket.user?.isAdmin then sendOnlineUser()

    # 断开连接
    socket.on 'disconnect', ->
        sendOnlineUser()

# 启动服务器
server.listen 8002, '127.0.0.1'