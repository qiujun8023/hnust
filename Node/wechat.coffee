# 第三方模块
request = require('request')

# 自定义模块
config    = require(__dirname + '/config')
youtu     = require(__dirname + '/youtu')
wechatApi = require(__dirname + '/wechatApi')

# log4js日志
logger = config.logger

# 微信SDK
apisdk = wechatApi.apisdk

# 请求数据
getData = (method, params, callback) ->
    params.secret = config.wechat.secret
    options =
        url : config.outUrl + "/weixin/" + method
        form: params
    request.post options, (err, res, body) ->
        if err then return callback err
        params.secret = undefined
        try
            body = JSON.parse body
        catch error
            logger.error "JSON解析失败：#{error}"
            logger.error "JSON如下：#{body}"
            return callback "JSON解析失败"
        callback null, body

# 图片识别
faceidentify = (sid, url, callback) ->
    youtu url, (err, res) ->
        if err then return apisdk.send
            touser:sid
        ,
            msgtype: 'text',
            text   :
                content: err
        , callback
        params =
            sid: sid
            uid: sid
            res: res
        getData 'face', params, (err, res) ->
            if err then res = code:-1
            res.code = parseInt res.code
            if res.code is 0
                msg =
                    msgtype: 'news'
                    news   :
                        articles: res.data
            else
                msg =
                    msgtype: 'text',
                    text   :
                       content: res.msg || '服务器发生错误，请稍后再试...'
            apisdk.send touser:sid, msg, callback

# 消息回复
message = (msg, session, callback) ->
    # 分析消息关键词
    switch msg.MsgType
        when 'text'  then key = msg.Content
        when 'image'
            key = '识图'
            pic = msg.PicUrl
        when 'event'
            session.state = ''
            switch msg.Event
                when 'click' then key = msg.EventKey
                when 'subscribe', 'unsubscribe' then key = '关注'
                else return callback msg
        else return callback msg

    params =
        uid: msg.FromUserName
        sid: msg.FromUserName
    # 返回主菜单
    if key in ['?', '？', '菜单', '首页', '索引']
        session.state = ''
        answer = '已经成功返回主菜单'
    else if key is '识图'
        session.state = ''
        answer = '正在进行识别，请稍候...'
        faceidentify params.sid, pic, (err) ->
            if err then logger.error err
    # 密码状态
    else if session.state is '输入密码'
        method = session.last.method
        params = session.last.params
        params.passwd = key
    # 绑定那个Ta
    else if session.state is '绑定Ta'
        method = session.last.method
        params = session.last.params
        params.sid = key
    # 未匹配到状态进入关键词匹配
    else
        # 我
        if key in ['我的成绩', '我的课表', '我的考试']
            key = key.replace /^我的/, ''
        # Ta
        else if key in ['Ta的成绩', 'Ta的课表', 'Ta的考试']
            if !session.ta
                session.state = '绑定Ta'
                answer = "请回复Ta的10位学号："
            params.sid = session.ta
            key = key.replace /^Ta的/, ''

        # 关键词转模块名
        switch key
            when '关注' then method = 'follow'
            when '成绩' then method = 'score'
            when '课表' then method = 'schedule'
            when '考试' then method = 'exam'
            when '解绑Ta'
                session.ta = ''
                answer = "解绑Ta完成"
            else
                if !key then return callback msg
                method = 'student'
                params.key = if key is '学生' then '' else key

    # 记录本次请求
    session.last =
        method: method
        params: params

    # 已有回复则退出
    if answer then return callback null, answer, session

    # 获取回复数据
    getData method, params, (err, res) ->
        if err then return callback err
        res.code = parseInt res.code
        switch res.code
            when -1
                return callback null, res.msg, session
            when  0
                if session.state is '绑定Ta'
                    session.ta = params.sid
                if session.state in ['输入密码', '绑定Ta']
                    session.state = ''
                return callback null, res.data, session
            when  4
                session.state = '输入密码'
                return callback null, res.msg, session
            else return callback res.msg

# 微信消息处理
wechat = (req, res, next) ->
    msg = req.weixin
    mmcKey = "wechat_session_#{msg.FromUserName}"
    config.mmc.get mmcKey, (err, session) ->
        if err then return res.reply '服务器错误。'
        message msg, session || {}, (err, answer, session) ->
            if err
                logger.error 'message error', err
                return res.reply ''
            # 缓存临时数据
            config.mmc.set mmcKey, session, 0, (err) ->
                if err then logger.error 'memcache error', err
            res.reply answer

module.exports = wechat