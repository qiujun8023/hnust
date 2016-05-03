#第三方模块
request = require('request')

# 自定义模块
config  = require(__dirname + '/config')
apisdk  = require(__dirname + '/wechatApi').apisdk

# log4js日志
logger  = config.logger

qqAvatar = (uid, callback) ->
    logger.debug "请求#{uid}的QQ头像"
    # avatar = "http://qlogo.store.qq.com/qzone/#{uid}/#{uid}/100"
    avatar = "http://q.qlogo.cn/headimg_dl?fid=blog&spec=100&dst_uin=#{uid}"
    callback null, avatar

qyAvatar = (uid, callback) ->
    logger.debug "请求#{uid}的微信头像"
    apisdk.getUser uid, (err, res) ->
        if err or !res.avatar
            return callback err || '未找到用户头像'
        callback null, res.avatar

exports.route  = (req, res) ->
    callback = (err, url) ->
        res.set 'Cache-Control': 'max-age=2592000'
        if err then return res.sendFile config.defaultAvatar
        request.get url, (err, res, body) ->
            if err then return res.sendFile config.defaultAvatar
        .pipe(res)

    # 判断来源
    switch req.params.from
        when 'qq' then qqAvatar req.params.uid, callback
        when 'qy' then qyAvatar req.params.uid, callback
        else res.sendFile config.defaultAvatar