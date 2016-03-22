# 第三方模块
path      = require('path')
log4js    = require('log4js')
memcached = require('memcached')

# 企业号相关信息
exports.wechat =
    encodingAESKey: 'encodingAESKey',
    token  : 'token',
    corpId : 'corpId'
    secret : 'secret'
    agentid: 'agentid'
    partyid: 'partyid'

# 优图图像识别相关信息
exports.tencentyun  =
    appid     : 'appid'
    secretId  : 'secretId'
    secretKey : 'secretKey'
    userid    : 'userid'
    faceGroups: []

# 本机内外网地址
exports.inUrl  = 'http://in.hnust.ticknet.cn'
exports.outUrl = 'http://hnust.ticknet.cn'

# 临时文件目录
exports.tempUrl  = 'http://hnust.ticknet.cn/runtime/temp'
exports.tempPath = path.resolve __dirname, '../runtime/temp'

# 配置日志输出
logger = log4js.getLogger()
logger.setLevel('info')
exports.logger = logger

# 配置缓存
exports.mmc = new memcached('127.0.0.1:11211')