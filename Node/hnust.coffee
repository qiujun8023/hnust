# 第三方模块
express    = require('express')
http       = require('http')
bodyParser = require('body-parser')
wechatLib  = require('wechat-enterprise')

# 自定义模块
config     = require(__dirname + '/config')
socket     = require(__dirname + '/socket')
wechat     = require(__dirname + '/wechat')
wechatApi  = require(__dirname + '/wechatApi')
avatar     = require(__dirname + '/avatar')

# 服务器
app    = express()
server = http.createServer(app)

# 处理JSON
app.use bodyParser.json
    limit: '1mb'
app.use bodyParser.urlencoded
    extended: true

# 微信企业号回调
app.use '/wechat/callback', wechatLib config.wechat, wechat

# 企业号API
app.all '/wechat/:apiName', wechatApi.route

# 头像
app.all '/avatar/:from/:uid?', avatar.route

# Socket相关
socket.start server
app.all '/socket/:room', socket.route

# 启动服务器
server.listen 8002, '127.0.0.1'