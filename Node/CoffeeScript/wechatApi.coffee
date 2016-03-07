# 第三方模块
APISDK = require('wechat-enterprise').API

# 自定义模块
config = require('./config')

# log4js日志
logger = config.logger

# 配置API
corpId  = config.wechat.corpId
secret  = config.wechat.secret
agentid = config.wechat.agentid
partyid = config.wechat.partyid
apisdk  = new APISDK(corpId, secret, agentid)

module.exports = (req, res) ->
    body = req.body

    # 错误格式化
    errFormat = (errmsg) ->
        errcode: -1
        errmsg : errmsg

    # 检查API调用权限
    if body.secret isnt secret
        return res.send errFormat 'Secret Error'

    # 回调函数定义
    callback = (err, result) ->
        if err
            result = errFormat err
            logger.error err
        res.send result

    # API模块判断
    switch req.params.apiName
        when 'getUser'
            logger.info "请求用户#{body.uid}的信息"
            apisdk.getUser body.uid, callback
        when 'createUser', 'updateUser'
            body.user ||= {}
            body.user.department = partyid
            logger.info "创建用户#{body.user?.userid}"
            apisdk.createUser body.user, (err, result) ->
                if err and err.code is 60102
                    logger.info "用户#{body.user?.userid}已存在，更新用户数据"
                    apisdk.updateUser body.user, callback
                else
                    callback err, result
        when 'deleteUser'
            logger.info "删除用户#{body.uid}"
            apisdk.deleteUser body.uid, callback
        when 'sendMsg'
            logger.info "主动发消息给", body.to
            apisdk.send body.to, body.message, callback
        else res.send errFormat '未找到相关API'