# 第三方模块
path   = require('path')
log4js = require('log4js')
redis  = require('redis')

# 配置缓存
exports.cache = redis.createClient()

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

# 临时文件目录
exports.baseUrl  = 'https://hnust.ticknet.cn'
exports.tempPath = path.resolve __dirname, '../runtime/temp'

# 配置日志输出
logger = log4js.getLogger()
logger.setLevel('warn')
exports.logger = logger

# 默认头像地址
exports.defaultAvatar = path.resolve __dirname, '../static/src/img/user.png'