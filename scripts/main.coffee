#AngularJS
hnust = angular.module 'hnust', ['ngRoute', 'ngAnimate', 'angularFileUpload']

#加载jsonp获取数据
hnust.factory 'request', ($rootScope, $http, $location) ->
    #检查数据
    check: (res, callback) ->
        self = this
        callback ||= ->
        res.code = parseInt(res.code)
        #跳转到登录
        if res.code is -2
            $rootScope.user =
                name: '游客'
                rank: -1
            $rootScope.referer = $location.url()
            $location.url '/login'
        #错误提示
        else if res.code is -1
            error = res.msg || '网络连接超时 OR 服务器错误。'
        else if res.code is 1
            layer.msg res.msg
        #确认框
        else if res.code is 2
            layer.open
                title: res.msg
                content:res.data
        #跳转到登录前页面
        else if res.code is  3
            if $rootScope.referer and $rootScope.referer isnt '/login'
                $location.url $rootScope.referer
                $rootScope.referer = ''
            else
                $location.url '/schedule'

        if res.code is 4
            res.params ||= {}
            layer.prompt
                formType: 1
                title: res.msg
                cancel: ->
                    callback '密码错误。', {}, {}
                    $rootScope.$digest()
            , (value, index, elem) ->
                layer.close index
                res.params.passwd = value
                self.query res.params, res.timeout, callback
                $rootScope.$digest()
        else callback error, res.info, res.data

    #请求数据
    query: (params, timeout, callback) ->
        self = this
        #jsonp请求参数
        search = angular.copy $location.search()
        search.fun ||= $rootScope.fun
        params = $.extend search, params
        params.callback = 'JSON_CALLBACK'

        timeout ||= 10000
        callback = if callback? then callback else ->

        #jsonp请求数据
        $http.jsonp $rootScope.url,
            params : params
            timeout: timeout
        .success (res) ->
            res.params  = params
            res.timeout = timeout
            self.check res, callback
        .error ->
            callback '网络异常，请稍后再试。'

hnust.factory 'animate', ->
    rand: ->
        animates = [
            'scale'
            'fade up'
            'fade left'
            'fade right'
            'slide down'
            'slide up'
            'slide left'
            'slide right'
        ] 
        animates[Math.floor Math.random() * animates.length]

hnust.animation '.animate', (animate)->
    enter: (element, done) ->
        element.transition "#{animate.rand()} in", done
        return

    leave: (element, done) ->
        element.transition 'scale out', 0, done
        return

hnust.directive 'autocomplete', ($timeout, request) ->
    link: ($scope, elem, attrs) ->
        $scope.keys = []
        #自动补全框
        $scope.dropdown = (method) ->
            dropdown = $('.ui.search.dropdown .menu')
            styles   = dropdown.attr('class')
            if method is 'hide'
                if styles.indexOf('hidden') is -1
                    dropdown.transition 'slide down out'
            else if styles.indexOf('visible') is -1
                dropdown.transition 'slide down in'
            return

        #检查输入框值的变化
        $scope.check = (key) ->
            $timeout ->
                if key is $scope.key
                    $scope.completion key
            , 300

        #自动补全
        $scope.completion = (key) ->
            if !key then return $scope.keys = []
            request.query {method:'key', key:key}, 5000, (error, info, data) ->
                if error or info.key != $scope.key
                    return
                $scope.keys = data
                if $scope.keys.length > 1
                    $scope.dropdown 'show'
                else if $scope.keys.length is 1 and $scope.keys[0] isnt $scope.key
                    $scope.dropdown 'show'
                else
                    $scope.dropdown 'hide'

        elem.on 'mouseenter', ->
            if $scope.keys.length
                $scope.dropdown 'show'

        elem.on 'mouseleave', ->
            $scope.dropdown 'hide'

        elem.on 'keydown', (event) ->
            if event.which is 13 then $scope.dropdown 'hide'

hnust.config ($httpProvider, $routeProvider, $animateProvider) ->
    $animateProvider.classNameFilter /animate/
    #设置路由
    $routeProvider
        .when '/login',
            fun: 'login',
            title: '用户登录',
            controller: 'login',
            templateUrl: 'views/login.html?150818'
        .when '/agreement',
            fun: 'agreement',
            title: '用户使用协议',
            templateUrl: 'views/agreement.html?150818'
        .when '/user',
            fun: 'user',
            title: '用户中心',
            controller: 'user',
            templateUrl: 'views/user.html?150818'
        .when '/schedule',
            fun: 'schedule',
            title: '实时课表',
            controller: 'schedule',
            templateUrl: 'views/schedule.html?150818'
        .when '/score',
            fun: 'score',
            title: '成绩查询',
            controller: 'score',
            templateUrl: 'views/score.html?150818'
        .when '/scoreAll',
            fun: 'scoreAll',
            title: '全班成绩',
            controller: 'scoreAll',
            templateUrl: 'views/scoreAll.html?150818'
        .when '/exam',
            fun: 'exam',
            title: '考试安排',
            controller: 'exam',
            templateUrl: 'views/exam.html?150818'
        .when '/credit', 
            fun: 'credit',
            title: '学分绩点',
            controller: 'credit',
            templateUrl: 'views/credit.html?150818'
        .when '/classroom', 
            fun: 'classroom',
            title: '空闲教室',
            controller: 'classroom',
            templateUrl: 'views/classroom.html?150818'
        .when '/elective', 
            fun: 'elective',
            title: '选课平台',
            controller: 'elective',
            templateUrl: 'views/elective.html?150818'
        .when '/judge', 
            fun: 'judge',
            title: '教学评价',
            controller: 'judge',
            templateUrl: 'views/judge.html?150818'
        .when '/book', 
            fun: 'book',
            title: '图书借阅',
            controller: 'book',
            templateUrl: 'views/book.html?150816'
        .when '/tuition', 
            fun: 'tuition',
            title: '学年学费',
            controller: 'tuition',
            templateUrl: 'views/tuition.html?150818'
        .when '/card', 
            fun: 'card',
            title: '一卡通',
            controller: 'card',
            templateUrl: 'views/card.html?150818'
        .when '/failRate', 
            fun: 'failRate',
            title: '挂科率',
            controller: 'failRate',
            templateUrl: 'views/failRate.html?150818'
        .when '/admin', 
            fun: 'admin',
            title: '后台管理',
            controller: 'admin',
            templateUrl: 'views/admin.html?150818'
        .otherwise
            redirectTo: '/schedule'

hnust.run ($rootScope, $location, request) ->
    #API网址
    $rootScope.domain = 'a.hnust.sinaapp.com'
    $rootScope.url    = 'http://' + $rootScope.domain + '/index.php'
    #修改title
    $rootScope.$on '$routeChangeSuccess', (event, current, previous) ->
        $rootScope.fun   = current.$$route?.fun   || ''
        $rootScope.title = current.$$route?.title || ''

    #获取用户信息
    $rootScope.$on 'updateUserInfo', (event, current) ->
        request.query fun:'user', 10000, (error, info, data) ->
            if error then info = {}
            info.id = info.studentId || ''
            info.name ||= '游客'
            info.rank = if info.rank then parseInt(info.rank) else -1
            info.scoreRemind = !!parseInt(info.scoreRemind)
            $rootScope.user = info
            if info and info.ws
                $rootScope.WebSocket info.ws

    #实时日志WebSockets
    $rootScope.WebSocket = (ws)->
        $rootScope.ws ||= new WebSocket(ws)
        $rootScope.ws.onopen = ->
            console.log 'WebSocket Open'
        $rootScope.ws.onmessage = (msg) ->
            msg = angular.fromJson(msg.data);
            request.check msg, (error, info ,data) ->
                if info and info.fun is 'onlineUser'
                    $rootScope.onlineUser =
                        info :info
                        data :data
                        error:error
                    $rootScope.$digest();
            console.log 'WebSocket Message'
        $rootScope.ws.onclose = ->
            $rootScope.ws = null
            $rootScope.onlineUser = error:'已断开网络连接'
            $rootScope.$digest();
            console.log 'WebSocket Close'

    #发送WebSockets消息
    $rootScope.sendMsg = (name, studentId, rank) ->
        layer.prompt
            formType: 2
            title: "发送给 #{name} ："
        , (value, index, elem) ->
            layer.close index
            $rootScope.ws.send angular.toJson
                msg:value
                name:name
                studentId:studentId
                rank:rank

#导航栏控制器
navbarController = ($scope, $rootScope, request) ->
    #加载layer扩展方法
    layer.config
        extend: 'extend/layer.ext.js'

    #顶栏
    $('.desktop.only.dropdown').dropdown
        on:'hover'
        action:'select'

    #侧栏
    $('.ui.sidebar').sidebar 'attach events', '#menu'
    $scope.$on '$routeChangeSuccess', ->
        $('.ui.sidebar').sidebar 'hide'
        if $rootScope.ws is null then $scope.$emit 'updateUserInfo'

    #是否隐藏导航栏
    UA = navigator.userAgent
    if UA.indexOf('demo') isnt -1 or UA.indexOf('hnust') isnt -1
        $scope.hideNavbar = true
    #获取用户信息
    $scope.$emit 'updateUserInfo'
    #注销登录
    $scope.logout = ->
        request.query fun:'logout'

#用户中心
userController = ($scope, $rootScope, $location, request) ->
    $('.ui.checkbox').checkbox 'uncheck'

    #邮件输入框的显示与不显示
    $scope.scoreRemind = (isCheck) ->
        mailField = $('#mailField')
        checkbox  = $('.ui.checkbox')
        $scope.user.scoreRemind = if isCheck? then isCheck else !$scope.user?.scoreRemind
        if $scope.user.scoreRemind is true
            checkbox.checkbox 'check'
            if mailField.attr('class').indexOf('visible') is -1
                mailField.transition 'slide down in'
            $scope.user.mail = $rootScope.user.mail
        else
            checkbox.checkbox 'uncheck'
            if mailField.attr('class').indexOf('hidden') is -1
                mailField.transition 'slide down out'
            $scope.user.mail = ''

    #监视有无获取用户信息
    deleteWatch = $scope.$watch ->
        $rootScope.user
    , ->
        if $rootScope.user?.rank? and $rootScope.user.rank isnt -1
            $scope.user = angular.copy $rootScope.user
            $scope.scoreRemind $scope.user.scoreRemind
            deleteWatch()
    , true

    #邮件校验
    $('.ui.form').form
        mail: 
            identifier: 'mail'
            optional   : true,
            rules: [
                type  : 'email'
                prompt: '请输入正确的邮件地址。'
            ]
    ,
        inline: true
        on    : 'blur'
        onSuccess: ->
            params =
                fun: 'userUpdate'
                scoreRemind: if $scope.user.scoreRemind then '1' else '0'
                mail: $scope.user.mail
            $scope.error = ''
            $scope.loading = true
            request.query params, 10000, (error, info, data) ->
                $scope.loading = false
                $scope.error = error
                if !error then $scope.$emit 'updateUserInfo'
            return false

#登录
loginController = ($scope, $rootScope, $location, request) ->
    $('.ui.checkbox').checkbox()
    if $rootScope.user?.rank? and $rootScope.user.rank isnt -1
        $location.url '/schedule'
    $scope.studentId = $scope.passwd = ''

    #用户名及密码等表单校验
    $('.ui.form').attr 'action', $rootScope.url
    $('.ui.form').form
        studentId: 
            identifier: 'studentId'
            rules: [
                type  : 'empty'
                prompt: '学号不能为空！'
            ,
                type  : 'length[10]'
                prompt: '学号不能少于10位！'
            ,
                type  : 'maxLength[10]'
                prompt: '学号不能大于10位！'
            ]
        , passwd: 
            identifier: 'passwd'
            rules: [
                type  : 'empty'
                prompt: '密码不能为空！'
            ]
        , agreement: 
            identifier: 'agreement'
            rules: [
                type  : 'checked'
                prompt: '同意用户使用协议方可使用！'
            ]
    , 
        inline: true
        on    : 'blur'
        onSuccess: ->
            params = 
                fun : 'login'
                passwd : $scope.passwd
                studentId : $scope.studentId
            $scope.error = ''
            $scope.loading = true
            request.query params, 10000, (error, info, data) ->
                $scope.loading = false
                $scope.error = error
                if !error then $scope.$emit 'updateUserInfo'

#成绩
scoreController = ($scope, request) ->
    $scope.error = ''
    $scope.loading = true
    request.query {}, 10000, (error, info, data) ->
        $scope.loading = false
        $scope.error = error
        $scope.data  = data
        if error then return
        $scope.terms = (k for k,v of $scope.data).sort (a, b) ->
            a < b

#全班成绩
scoreAllController = ($scope, $location, request) ->
    if !$location.search().course
        return $location.url '/score'
    $scope.error = ''
    $scope.loading = true
    request.query {}, 10000, (error, info, data) ->
        $scope.loading = false
        $scope.error = error
        $scope.info  = info
        $scope.data  = data

#课表
scheduleController = ($scope, $timeout, request) ->
    $scope.error = ''
    $scope.loading = true
    request.query {}, 10000, (error, info, data) ->
        $scope.loading = false
        $scope.error = error
        $scope.info  = info
        $scope.data  = data
        $timeout ->
            $('.menu .item').tab()
            $('.ui.inline.dropdown').dropdown()

#考试
examController = ($scope, request) ->
    $scope.error = ''
    $scope.loading = true
    request.query {}, 10000, (error, info, data) ->
        $scope.loading = false
        $scope.error = error
        $scope.data  = data

#学分绩点
creditController = ($scope, request) ->
    $scope.error = ''
    $scope.loading = true
    request.query {}, 10000, (error, info, data) ->
        $scope.loading = false
        $scope.error = error
        $scope.data  = data

#空闲教室
classroomConller = ($scope, $rootScope, $timeout, request) ->
    #阿拉伯转汉字
    $scope.nums = 
        '1'  : '一'
        '2'  : '二'
        '3'  : '三'
        '4'  : '四'
        '5'  : '五'
        '6'  : '六'
        '7'  : '七'
        '8'  : '八'
        '9'  : '九'
        '10' : '十'
        '11' : '十一'
        '12' : '十二'
        '13' : '十三'
        '14' : '十四'
        '15' : '十五'
        '16' : '十六'
        '17' : '十七'
        '18' : '十八'
        '19' : '十九'
        '20' : '二十'

    #教学楼代码
    $scope.builds = [
        ['103', '第一教学楼']
        ['102', '第二教学楼']
        ['104', '第三教学楼']
        ['105', '第四教学楼']
        ['107', '第五教学楼']
        ['110', '第八教学楼']
        ['111', '第九教学楼']
        ['212', '第十教学楼']
        ['213', '第十教附一楼']
        ['214', '第十教附二楼']
    ]
    $scope.build = '103'

    #周次代码
    $scope.weeks = []
    for i in [1..20]
        $scope.weeks.push [i, "第#{$scope.nums[i]}周"]

    #星期代码
    $scope.days = []
    for i in [1...7]
        $scope.days.push [i, "星期#{$scope.nums[i]}"]
    $scope.days.push [7, '星期日']

    #开始节数
    $scope.beginSessions = []
    for i in [1..5]
        $scope.beginSessions.push [i, "#{$scope.nums[i * 2 -1]}#{$scope.nums[i * 2]}节"]

    #结束节数
    $scope.endSessions = []
    for i in [1..5]
        $scope.endSessions.push [i, "至#{$scope.nums[i * 2 -1]}#{$scope.nums[i * 2]}节"]

    #获取当前时间日期
    date   = new Date()
    week   = $rootScope.user?.week || 0
    month  = date.getMonth() + 1
    day    = if date.getDay() is 0 then 7 else date.getDay()
    hour   = date.getHours()
    minute = date.getMinutes()
    #判断夏季作息时间表
    isSummer = if month >= 5 and month < 10 then true else false
    if hour < 8 or hour is 8 and isSummer and minute < 30
        $scope.beginSession = 1
        $scope.endSession   = 1
    else if hour < 10 or hour is 10 and isSummer and minute < 30
        $scope.beginSession = 2
        $scope.endSession   = 2
    else if hour < 14 or hour is 14 and isSummer and minute < 30
        $scope.beginSession = 3
        $scope.endSession   = 3
    else if hour < 16 or hour is 16 and isSummer and minute < 30
        $scope.beginSession = 4
        $scope.endSession   = 4
    else if hour < 19 or hour is 19 and isSummer and minute < 30
        $scope.beginSession = 5
        $scope.endSession   = 5
    else
        $scope.beginSession = 1
        $scope.endSession   = 1
        week = if day is 7 then week + 1 else week
        day = if day is 7 then 1 else day + 1

    if !week
        $scope.week = 1
    else if week > 20
        $scope.week = 20
    else 
        $scope.week = week
    $scope.day = day

    $scope.search = ->
        $scope.error = ''
        if !$scope.build or !$scope.week or !$scope.day or !$scope.beginSession or !$scope.endSession
            return $scope.error = '请填写完整表单'
        params = 
            build: $scope.build
            week : $scope.week
            day  : $scope.day
            beginSession: $scope.beginSession
            endSession  : $scope.endSession
        $scope.loading = true
        request.query params, 10000, (error, info, data) ->
            $scope.loading = false
            $scope.error = error
            $scope.data  = data

#选课平台
electiveConller = ($scope, $rootScope, $timeout, request) ->
    $('.tabular .item').tab()

    #个人信息
    $scope.person = loading:true
    request.query {}, 30000, (error, info, data) ->
        $scope.person.error = error
        $scope.person.logs  = data?.logs
        $scope.person.courses = data?.courses
        $scope.person.loading = false

    #选/退
    $scope.action = (title, url) ->
        if !confirm('您确定要' + title + '吗？') then return
        params =
            method : 'action'
            title  : title
            url    : url
        request.query params, 10000, (error, info, data) ->
            if angular.isObject data.data and !angular.isArray data.data
                $scope.person.logs.push data.data

    #选课列表
    $scope.search = (key, page) ->
        if key then $scope.key = key
        $scope.list = loading:true
        params = 
            method : 'list'
            key    : $scope.key
            page   : if page and page > 0 then page else 1
        request.query params, 10000, (error, info, data) ->
            $scope.list.error = error
            $scope.list.info  = info
            $scope.list.data  = data
            $scope.list.loading = false
    $scope.search()

#教学评价
judgeController = ($scope, $rootScope, request) ->
    $scope.error = ''
    $scope.loading = true
    request.query {}, 10000, (error, info, data) ->
        $scope.loading = false
        $scope.error = error
        $scope.data  = data

    #评教
    $scope.judge = (item) ->
        $('.ui.checkbox').checkbox()
        $('.ui.form').form 'clear'
        $scope.judging = item

    #提交评价
    $scope.submit = ->
        $scope.judging.error = ''
        $scope.judging.loading = ''
        data = params: $scope.judging.params
        flag = true
        for i in [0...10]
            data["a#{i}"] = $("input[name='a#{i}']:checked").val()
            if !data["a#{i}"]
                layer.msg '请确定表单已填写完整。', shift:6
                return false
            if i isnt 0 and data["a#{i}"] isnt data["a#{i-1}"]
                flag = false
        if flag
            layer.msg '不能全部选择相同的选项。', shift:6
            return false
        params =
            fun  : 'judge'
            data : angular.toJson(data)
        request.query params, 10000, (error, info, data) ->
            if error
                $scope.judging.error = error
                $scope.judging.loading = false
            else
                $scope.judging = false
                $scope.data = data.data

#图书借阅
bookController = ($scope, $location, $timeout, request) ->
    $('.tabular .item').tab()
    #回车键Submit
    $('.ui.form').form {}, 
        onSuccess: ->
            $scope.$apply ->
                $scope.search()

    $scope.person = loading:true
    request.query {}, 10000, (error, info, data) ->
        $scope.person.loading = false
        $scope.person.error = error
        $scope.person.data = data

    #续借
    $scope.renew = (params) ->
        params.method = 'renew'
        request.query params, 10000, (data) ->
            $scope.data = data.data

    #搜索书列表
    $scope.search = (key, page) ->
        if key then $scope.key = key
        if !$scope.key?.length then return

        $scope.list = loading:true
        params =
            method : 'list'
            key    : $scope.key
            page   : if page and page > 0 then page else 1
        request.query params, 10000, (error, info, data) ->
            $scope.list.loading = false
            $scope.list.error = error
            $scope.list.info  = info
            $scope.list.data  = data
            $timeout ->
                $('.ui.accordion').accordion
                    duration: 200
                    exclusive: false

    #查找详细信息
    $scope.info = (item) ->
        if item.data or item.loading
            return
        item.loading = true
        request.query {method:'info', key:item.id}, 10000, (error, info, data) ->
            item.loading = false
            item.data = data

#学费
tuitionController = ($scope, $timeout, request) ->
    $scope.error = ''
    $scope.loading = true
    request.query {}, 10000, (error, info, data) ->
        $scope.loading = false
        $scope.error = error
        if error then return
        $scope.total = data?.total
        delete data?.total
        $scope.data = data
        $scope.terms = (k for k,v of $scope.data).sort (a, b) ->
            a < b

        $timeout ->
            $('.ui.positive.message').popup
                popup : $('.ui.flowing.popup')
                on    : 'hover'

#校园一卡通
cardController = ($scope, request) ->
    $scope.error = ''
    $scope.loading = true
    request.query {}, 10000, (error, info, data) ->
        $scope.loading = false
        $scope.error = error
        $scope.info  = info
        $scope.data  = data

    #挂失与解挂
    $scope.card = (method) ->
        msg = '您确定要' + if method is 'loss' then '挂失' else '解挂' + '吗？';
        if !confirm(msg)
            return
        params = 
            method: method
            cardId: $scope.info.cardId
        $scope.loading = true
        request.query params, 10000, (error, info, data) ->
            $scope.loading = false
            $scope.info = info

#挂科率统计
failRateController = ($scope, $rootScope, $timeout, request) ->
    #搜索
    $scope.search = (key) ->
        if key then $scope.key = key
        if !$scope.key then return
        #请求服务器数据
        $scope.error = ''
        $scope.loading = true
        request.query {key:$scope.key}, 10000, (error, info, data) ->
            $scope.loading = false
            $scope.error = error
            $scope.data = data
            #进度条显示
            $timeout ->
                $('.progress').progress()

    #进度条颜色
    $scope.progressColor = (rate)->
        if rate <= 2
            ['teal']
        else if rate <= 6
            ['green']
        else if rate <= 12
            ['pink']
        else if rate <= 20
            ['orange']
        else
            ['red']

#修改权限
adminController = ($scope, $rootScope, $location, $timeout, request, FileUploader) ->
    $('.tabular .item').tab()
    $scope.putApp = {}
    $scope.editUser = {}

    #计算文件大小
    $scope.readablizeBytes = (bytes) ->
        s = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB'];
        e = Math.floor Math.log(bytes) / Math.log(1024)
        (bytes / Math.pow(1024, Math.floor(e))).toFixed(2) + " " + s[e]

    #文件上传到七牛
    $scope.putApp.uploader = uploader = new FileUploader
        url:'http://upload.qiniu.com/'
    #添加文件
    uploader.onAfterAddingFile = (fileItem) ->
        uploader.queue.splice 0, uploader.queue.length - 1
        $scope.putApp.size = $scope.readablizeBytes uploader.queue[0]?.file.size
        $scope.putApp.name = uploader.queue[0]?.file.name + '  (' + $scope.putApp.size + ')'
    #上传成功
    uploader.onCompleteItem = (fileItem, response, status, headers) ->
        uploader.queue[0].isSuccess = false
        uploader.queue[0].isUploaded = false
        if status isnt 200
            $scope.putApp.loading = false
            return $scope.putApp.error = angular.toJson response
        #将上传数据反馈到服务器
        params =
            fun     : 'putApp'
            version : $scope.putApp.version
            intro   : $scope.putApp.intro
            size    : $scope.putApp.size
            url     : 'http://ypan.qiniudn.com/' + response.key
        request.query params, 10000, (error, info, data) ->
            $scope.putApp.loading = false
            $scope.lastUser.error = error
    #发布APP
    $('.ui.putApp.form').form
        version: 
            identifier: 'version'
            rules: [
                type  : 'empty'
                prompt: '版本号不能为空！'
            ]
        , intro: 
            identifier: 'intro'
            rules: [
                type  : 'empty'
                prompt: '介绍不能为空！'
            ]
    ,
        inline: true
        on    : 'blur'
        onSuccess: ->
            $scope.putApp.error = ''
            if !uploader.queue.length
                $scope.putApp.error = 'APK文件不能为空'
            else if !$rootScope.user || !$rootScope.user.qiniu
                $scope.putApp.error = '无七牛Token，请刷新或稍后再试'
            else
                $scope.putApp.loading = true
                uploader.queue[0].formData = [
                    key   : 'APP/' + uploader.queue[0].file.name
                    token : $rootScope.user?.qiniu
                ]
                uploader.uploadAll()
            $scope.$digest()
            return false

    #权限下拉框
    $('.ui.rank.dropdown').dropdown()
    #表单验证
    $('.ui.editUser.form').form
        studentId: 
            identifier: 'studentId'
            rules: [
                type  : 'empty'
                prompt: '学号不能为空！'
            ,
                type  : 'length[10]'
                prompt: '学号不能少于10位！'
            ,
                type  : 'maxLength[10]'
                prompt: '学号不能大于10位！'
            ]
        , rank: 
            identifier: 'rank'
            rules: [
                type  : 'empty'
                prompt: '权限不能为空！'
            ]
        ,
    ,
        inline: true
        on    : 'blur'
        onSuccess: ->
            params =
                fun: 'editUser'
                studentId: $scope.editUser.studentId
                rank     : $("select[name='rank']").val()
            $scope.editUser.error = ''
            $scope.editUser.loading = true
            request.query params, 10000, (error, info, data) ->
                $scope.editUser.loading = false
                $scope.editUser.error = error
            return false

    #最近使用用户
    $scope.lastUser = loading:true
    request.query fun:'lastUser', 10000, (error, info, data) ->
        $scope.lastUser.loading = false
        $scope.lastUser.error = error
        $scope.lastUser.data = data

#排序
sortByFilter = ->
    (items, predicate, reverse) ->
        items = _.sortBy items, (item) ->
            #将优中良差转为对应的数值进行比较
            if item[predicate] is '优'
                95.02
            else if item[predicate] is '良'
                84.02
            else if item[predicate] is '中'
                74.02
            else if item[predicate] is '及格'
                60.02
            else if item[predicate] is '不及格'
                0
            else if !isNaN(item[predicate]) && item[predicate]
                parseFloat(item[predicate])
            else
                item[predicate]
        if reverse then items else items.reverse()

#函数注入
hnust.controller 'navbar'     , navbarController
hnust.controller 'login'      , loginController
hnust.controller 'user'       , userController
hnust.controller 'score'      , scoreController
hnust.controller 'scoreAll'   , scoreAllController
hnust.controller 'schedule'   , scheduleController
hnust.controller 'exam'       , examController
hnust.controller 'credit'     , creditController
hnust.controller 'classroom'  , classroomConller
hnust.controller 'elective'   , electiveConller
hnust.controller 'judge'      , judgeController
hnust.controller 'book'       , bookController
hnust.controller 'tuition'    , tuitionController
hnust.controller 'card'       , cardController
hnust.controller 'failRate'   , failRateController
hnust.controller 'admin'      , adminController
hnust.filter     'sortBy'     , sortByFilter