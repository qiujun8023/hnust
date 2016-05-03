# 第三方模块
fs         = require('fs')
async      = require('async')
request    = require('request')
tencentyun = require('tencentyun')

# 自定义模块
config     = require(__dirname + '/config')

# log4js日志
logger = config.logger

# 配置优图相关信息
appid     = config.tencentyun.appid
secretId  = config.tencentyun.secretId
secretKey = config.tencentyun.secretKey
userid    = config.tencentyun.userid
tencentyun.conf.setAppInfo appid, secretId, secretKey, userid, 0
youtu     = tencentyun.youtu

# 错误信息
errorinfo =
    'HTTP_BAD_REQUEST'           : '请求不合法，包体格式错误'
    'HTTP_UNAUTHORIZED'          : '权限验证失败'
    'HTTP_FORBIDDEN'             : '鉴权信息不合法，禁止访问'
    'HTTP_INTERNAL_SERVER_ERROR' : '服务内部错误'
    'HTTP_SERVICE_UNAVAILABLE'   : '服务不可用'
    'HTTP_GATEWAY_TIME_OUT'      : '后端服务超时'
    'SDK_DISTANCE_ERROR'         : '相似度错误'
    'SDK_IMAGE_FACEDETECT_FAILED': '人脸检测失败'
    'SDK_IMAGE_DECODE_FAILED'    : '图片解码失败'
    'SDK_FEAT_PROCESSFAILED'     : '特征处理失败'
    'SDK_FACE_SHAPE_FAILED'      : '提取轮廓错误'
    'SDK_FACE_GENDER_FAILED'     : '提取性别错误'
    'SDK_FACE_EXPRESSION_FAILED' : '提取表情错误'
    'SDK_FACE_AGE_FAILED'        : '提取年龄错误'
    'SDK_FACE_POSE_FAILED'       : '提取姿态错误'
    'SDK_FACE_GLASS_FAILED'      : '提取眼镜错误'
    'STORAGE_ERROR'              : '存储错误'
    'ERROR_IMAGE_EMPTY'          : '图片为空'
    'ERROR_PARAMETER_EMPTY'      : '参数为空'
    'ERROR_PERSON_EXISTED'       : '个体已存在'
    'ERROR_PERSON_NOT_EXISTED'   : '个体不存在'
    'ERROR_PARAMETER_TOO_LONG'   : '参数过长'

# 获取图片并保存
getImage = (imgUrl, callback) ->
    logger.debug "正在获取图片..."
    timestamp = new Date().getTime()
    fileName  = "node_img_#{timestamp}"
    tempPath  = "#{config.tempPath}/#{fileName}"
    request.get imgUrl, (err) ->
        callback err, tempPath
    .pipe(fs.createWriteStream(tempPath))

# 单个人脸识别
faceIdentify = (facePath, groupId, retried, callback) ->
    # 优图人脸识别
    youtu.faceidentify facePath, groupId, (data) ->
        # 判断网络异常
        if data.code isnt 200
            data.data =
                errorcode: -1
                errormsg : data.message
        # 判断腾讯返回结果
        data = data.data
        if data.errorcode is 0
            callback null, data.candidates
        else if !retried and (data.errorcode is -1 or data.errormsg is 'SDK_IMAGE_DECODE_FAILED')
            faceIdentify facePath, groupId, true, callback
        else
            callback errorinfo[data.errormsg] || data.errormsg

# 并发人脸识别
faceIdentifyAll = (facePath, callbackAll) ->
    logger.debug "正在进行人脸识别..."
    mapGroups  = []
    faceGroups = config.tencentyun.faceGroups
    for groupId in faceGroups
        mapGroups.push
            facePath: facePath
            groupId: groupId
    # 并发
    async.concat mapGroups, (item, callback) ->
        faceIdentify item.facePath, item.groupId, false, callback
    , callbackAll

# 处理结果
getResult = (result, callback) ->
    logger.debug "正在处理识别结果..."
    result.sort (a, b) ->
        return b.confidence - a.confidence
    callback null, result.slice 0, 10


# 图片识别并返回结果
module.exports = (pic, callback) ->
    tasks = [
        (cb) -> cb(null, pic)
        getImage
        faceIdentifyAll
        getResult
    ]
    async.waterfall tasks, callback