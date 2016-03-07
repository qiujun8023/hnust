#AngularJS
hnust = angular.module 'hnust', ['ngRoute', 'ngCookies', 'ngAnimate', 'ngMd5', 'bw.paging']

#加载服务器数据
hnust.factory 'request', ($rootScope, $http, $location, $cookies) ->
    #检查数据
    check: (res, callback) ->
        self = this
        callback ||= ->
        switch parseInt(res.code)
            #跳转到登录
            when -2
                $rootScope.user = rank:0
                $cookies.prompt = error = res.msg || '您需要登陆才能访问'
                $cookies.referer ||= window.location.href
                window.location.href = '/#/login'
            #错误提示
            when -1
                error = res.msg || '网络连接超时 OR 服务器错误。'
            #普通提示
            when 1
                layer.msg res.msg
            #确认框
            when 2
                layer.open
                    title: res.msg
                    content:res.data
            #跳转到登录前页面
            when 3
                if $cookies.referer and 'login' not in $cookies.referer
                    window.location.href = $cookies.referer
                else
                    $location.url '/schedule'
                $cookies.referer = $cookies.prompt = ''
            #需要输入密码
            when 4
                layer.prompt
                    formType: 1
                    title: res.msg
                    cancel: ->
                        $rootScope.$apply ->
                            callback '密码错误。', {}, {}
                , (value, index, elem) ->
                    $rootScope.$apply ->
                        layer.close index
                        res.req.data ||= {}
                        res.req.data.passwd = value
                        self.query res.req, callback
                return
        callback error, res.info, res.data

    #请求数据
    query: (req, callback) ->
        self = this
        reqBak = angular.copy req
        #get参数
        search = angular.copy $location.search()
        search.module ||= $rootScope.module
        search.method ||= $rootScope.method
        req.params = $.extend search, req.params || {}
        #post参数
        if req.data and angular.isObject req.data
            req.data = $.param req.data
        req.method = if req.data and req.data.length then 'POST' else 'GET'
        #网址
        req.url ||= $rootScope.url + req.params.module + '/' + req.params.method
        req.params.module = req.params.method = undefined
        #超时时间
        req.timeout ||= 10000
        #回调函数
        callback ||= ->

        #发起请求
        $http(req)
            .success (res) ->
                res = if angular.isObject res then res else code:-1
                res.req = reqBak
                self.check res, callback
            .error ->
                callback '网络异常，请稍后再试。'

hnust.factory 'utils', ->
    units   : '个十百千万@#%亿^&~'
    chars   : '零一二三四五六七八九'
    numToZh : (number) ->
        a = (number + '').split('')
        s = []
        t = this
        if (a.length > 12) then return throw new Error('too big')

        j = a.length - 1
        for i in [0..j]
            if j not in [1, 5, 9] or i isnt 0 or a[i] isnt '1'
                s.push(t.chars.charAt(a[i]))
            if i isnt j then s.push(t.units.charAt(j-i));

        s.join('').replace /零([十百千万亿@#%^&~])/g, (m, d, b) ->
            b = t.units.indexOf(d);
            if b is -1
                ''
            else if d in ['亿', '万']
                d
            else if a[j-b] is '0'
                '零'
        .replace /零+/g, '零'
        .replace /零([万亿])/g, (m, b) ->
            b
        .replace /亿[万千百]/g, '亿'
        .replace /[零]$/, ''
        .replace /[@#%^&~]/g, (m) ->
            return {'@':'十', '#':'百', '%':'千', '^':'十', '&':'百', '~':'千'}[m]
        .replace /([亿万])([一-九])/g, (m, d, b, c) ->
            c = t.units.indexOf(d);
            if c is -1
                m
            else if a[j-c] is '0'
                d + '零' + b

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
        element.transition "#{animate.rand()} in", 300, done
        return

    leave: (element, done) ->
        element.transition 'scale out', 0, done
        return

hnust.directive 'others', ($location, $rootScope) ->
    restrict: 'EA'
    templateUrl: 'static/views/index/others.html?151006'
    replace:true
    link: ($scope, elem, attrs) ->
        #监视学号的变化
        $scope.sid = $location.search().sid || $rootScope.user.uid || ''
        $scope.$watch ->
            return $scope.info
        , ->
            $scope.sid = $scope.info?.sid || $scope.sid || ''
        , true
        #查询他人
        $('.others.form').form {},
            onSuccess: ->
                $scope.$apply ->
                    search = $location.search()
                    search.sid = $scope.sid
                    $location.search(search)

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
            request.query
                params:
                    type: 'key'
                    key : key
            , (error, info, data) ->
                if error or info.key != $scope.key
                    return
                $scope.keys = data
                if $scope.keys.length > 1
                    $scope.dropdown 'show'
                else if $scope.keys.length is 1 and $scope.keys[0] isnt $scope.key
                    $scope.dropdown 'show'
                else
                    $scope.dropdown 'hide'

        elem.on 'click', ->
            if $scope.keys.length
                $scope.dropdown 'show'

        elem.on 'mouseleave', ->
            $scope.dropdown 'hide'

        elem.on 'keydown', (event) ->
            if event.which is 13 then $scope.dropdown 'hide'

hnust.config ($animateProvider, $httpProvider, $routeProvider) ->
    #过滤动画
    $animateProvider.classNameFilter /animate/
    #使用urlencode方式Post
    $httpProvider.defaults.headers.post['Content-Type'] = 'application/x-www-form-urlencoded';
    #设置路由
    $routeProvider
        .when '/login',
            title: '用户登录'
            module: 'user'
            controller: 'login'
            templateUrl: 'static/views/index/login.html?151104'
        .when '/agreement',
            title: '用户使用协议'
            templateUrl: 'static/views/index/agreement.html?151104'
        .when '/user',
            title: '用户中心'
            module: 'user'
            controller: 'user'
            templateUrl: 'static/views/index/user.html?151104'
        .when '/api',
            title: 'API文档'
            controller: 'api'
            templateUrl: 'static/views/index/api.html?151104'
        .when '/schedule',
            title: '实时课表'
            controller: 'schedule'
            templateUrl: 'static/views/index/schedule.html?151104'
        .when '/score',
            title: '成绩查询'
            controller: 'score'
            templateUrl: 'static/views/index/score.html?151207'
        .when '/scoreAll',
            title: '全班成绩'
            controller: 'scoreAll'
            templateUrl: 'static/views/index/scoreAll.html?151207'
        .when '/exam',
            title: '考试安排'
            controller: 'exam'
            templateUrl: 'static/views/index/exam.html?151104'
        .when '/credit',
            title: '学分绩点'
            controller: 'credit'
            templateUrl: 'static/views/index/credit.html?151104'
        .when '/classroom',
            title: '空闲教室'
            controller: 'classroom'
            templateUrl: 'static/views/index/classroom.html?151104'
        .when '/elective',
            title: '选课平台'
            controller: 'elective'
            templateUrl: 'static/views/index/elective.html?160229'
        .when '/judge',
            title: '教学评价'
            controller: 'judge'
            templateUrl: 'static/views/index/judge.html?151115'
        .when '/rank',
            title: '成绩排名'
            controller: 'rank'
            templateUrl: 'static/views/index/rank.html?151104'
        .when '/book',
            title: '图书借阅'
            controller: 'book'
            templateUrl: 'static/views/index/book.html?151104'
        .when '/tuition',
            title: '学年学费'
            controller: 'tuition'
            templateUrl: 'static/views/index/tuition.html?151104'
        .when '/card',
            title: '一卡通'
            controller: 'card'
            templateUrl: 'static/views/index/card.html?151104'
        .when '/failRate',
            title: '挂科率'
            module: 'data'
            controller: 'failRate'
            templateUrl: 'static/views/index/failRate.html?151104'
        .otherwise
            redirectTo: '/schedule'

hnust.run ($rootScope, $cookies, request) ->
    #初始化数据
    $rootScope.url  = ''
    $rootScope.user = rank: 0

    #客户端相关
    tick = $rootScope.tick = window.tick || {}
    tick.setTitle ||= ->
    tick.isClient ||= navigator.userAgent.indexOf('hnust') isnt -1
    tick.version  ||= if tick.getVersion then tick.getVersion() else 'v0.0.0'

    #监视路由成功事件
    $rootScope.$on '$routeChangeSuccess', (event, current, previous) ->
        $rootScope.module = current.$$route?.module || 'student'
        $rootScope.method = current.$$route?.controller || ''
        $rootScope.title  = current.$$route?.title  || ''
        $rootScope.tick.setTitle $rootScope.title

    #获取用户信息
    $rootScope.$on 'updateUserInfo', (event, current) ->
        request.query
            params:
                module: 'user'
                method: 'user'
        , (error, info, data) ->
            if error then return
            $rootScope.user = info

    #WebSocket
    $rootScope.$watch ->
        $cookies.token
    , (token) ->
        socket = $rootScope.socket
        if token and !socket
            socket = $rootScope.socket = io.connect()
        else if token and socket.disconnected
            socket.connect()
        else if !token and socket
            return socket.close()
        else return

        #连接时发送Token
        socket.on 'connect', ->
            socket.emit 'token', token

        #彩蛋
        eggs = (content) ->
            if content.indexOf('下雪') isnt -1
                $.getScript('static/scripts/snow.src.js')
                $('.pusher').css('opacity','0.9')

        #HTML转义
        htmlEncode = (str) ->
            div = document.createElement('div')
            div.appendChild(document.createTextNode(str))
            div.innerHTML

        #消息
        socket.on 'push', (data) ->
            if !angular.isObject(data) then return
            if $rootScope.tick.version isnt 'v0.0.0'
                return eggs(data.content)
            data.type    = parseInt(data.type)
            data.title   = htmlEncode(data.title)
            data.content = "<pre>#{htmlEncode(data.content)}</pre><span>#{data.time.substr(5, 11)}</span>"
            #回调函数
            callback = (index) ->
                layer.close(index)
                eggs(data.content)
                socket.emit 'achieve', data.id
            #消息处理
            switch data.type
                when 0
                    layer.confirm data.content,
                        btn: ['确定']
                        title:data.title
                    , callback, callback
                when 1, 2
                    layer.confirm data.content,
                        btn: ['前往','关闭']
                        title: data.title
                    , (index) ->
                        callback(index)
                        if data.type is 1
                            window.location.href = data.success
                        else
                            index = layer.open
                                type   : 2
                                title  : data.title
                                minmax : true
                                content: data.success
                            layer.full index
                    , callback

#导航栏控制器
navbarController = ($scope, request) ->
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

    #获取用户信息
    $scope.$emit 'updateUserInfo'

    #注销登录
    $scope.logout = ->
        request.query
            params:
                module: 'user'
                method: 'logout'

#用户中心
userController = ($scope, $rootScope, md5, request) ->
    #监视有无用户信息变化
    $scope.$watch ->
        $rootScope.user
    , ->
        if $rootScope.user.rank <= 0 then return
        $scope.user = angular.copy $rootScope.user
    , true

    #表单校验
    $('.ui.accordion').accordion()
    $('input').popup on:'focus'
    $('.ui.form').form
        newPasswd:
            identifier: 'newPasswd'
            optional   : true,
            rules: [
                type  : 'length[6]'
                prompt: '新密码最少6位'
            ]
        newPasswd2:
            identifier: 'newPasswd2'
            rules: [
                type  : 'match[newPasswd]'
                prompt: '两次密码不一致。'
            ]
        mail:
            identifier: 'mail'
            rules: [
                type  : 'email'
                prompt: '请输入正确的邮件地址。'
            ]
        , phone:
            identifier: 'phone'
            rules: [
                type  : 'integer'
                prompt: '手机号码格式有误。'
            ,
                type  : 'length[11]'
                prompt: '手机号码长度不能少于11位。'
            ,
                type  : 'maxLength[11]'
                prompt: '手机号码长度不能大于11位。'
            ]
    ,
        inline: true
        on    : 'blur'
        onSuccess: ->
            $scope.error = ''
            $scope.loading = true
            request.query
                params:
                    module   : 'user'
                    method   : 'update'
                data :
                    mail     : $scope.user.mail
                    phone    : $scope.user.phone
                    oldPasswd: if $scope.oldPasswd then md5.createHash $scope.oldPasswd else ''
                    newPasswd: if $scope.newPasswd then md5.createHash $scope.newPasswd else ''
            , (error, info, data) ->
                $scope.loading = false
                if error then return $scope.error = error
                $scope.$emit 'updateUserInfo'
            return false

#登录
loginController = ($scope, $rootScope, $cookies, $location, md5, request) ->
    $('.ui.checkbox').checkbox()
    $('.wechat.label').popup
        position : 'top right',
        popup    : $('.wechat.popup')
        on       : 'click'
    if $rootScope.user.rank > 0
        return $location.url '/schedule'

    #登陆错误提示
    $scope.$watch ->
        $cookies.prompt
    , (prompt) ->
        $scope.prompt = prompt

    #用户名及密码等表单校验
    $('.ui.form').form
        uid:
            identifier: 'uid'
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
            $scope.error   = ''
            $scope.loading = true
            request.query
                params:
                    module: 'user'
                    method: 'login'
                data:
                    uid   : $scope.uid
                    passwd: md5.createHash $scope.passwd
            , (error, info, data) ->
                $scope.loading  = false
                if error then return $scope.error = error
                $rootScope.user = info

#成绩
apiController = ($scope) ->
    $scope.host = window.location.host;

#成绩
scoreController = ($scope, request) ->
    $('.ui.form').form()
    $scope.error   = ''
    $scope.loading = true
    request.query {}, (error, info, data) ->
        $scope.loading = false
        $scope.error   = error
        $scope.info    = info
        $scope.data    = data
        if error then return
        $scope.terms = (k for k,v of $scope.data).sort (a, b) ->
            a < b

#全班成绩
scoreAllController = ($scope, $location, request) ->
    if !$location.search().course
        return $location.url '/score'
    $scope.error   = ''
    $scope.loading = true
    request.query {}, (error, info, data) ->
        $scope.loading = false
        $scope.error   = error
        $scope.info    = info
        $scope.data    = data

#课表
scheduleController = ($scope, $timeout, request, utils) ->
    $scope.weeks = []
    for i in [0..20]
        $scope.weeks.push "第#{i}周"

    $scope.error   = ''
    $scope.loading = true
    request.query
        params:
            type:0
    , (error, info, data) ->
        $scope.loading = false
        $scope.error   = error
        $scope.info    = info
        $scope.data    = data
        $timeout ->
            day = (new Date).getDay()
            $('.ui.inline.dropdown').dropdown()
            $('.menu .item').tab('change tab', day || 7)

    $scope.hasCourse = (day) ->
        if !angular.isObject(day) then return false
        for tmp, item of day
            if angular.isObject(item) and item.length
                return true
        return false

#考试
examController = ($scope, request) ->
    $scope.error   = ''
    $scope.loading = true
    request.query {}, (error, info, data) ->
        $scope.loading = false
        $scope.error   = error
        $scope.info    = info
        $scope.data    = data

#学分绩点
creditController = ($scope, request) ->
    $scope.error   = ''
    $scope.loading = true
    request.query {}, (error, info, data) ->
        $scope.loading = false
        $scope.error   = error
        $scope.info    = info
        $scope.data    = data

#空闲教室
classroomConller = ($scope, $rootScope, request, utils) ->
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
        $scope.weeks.push [i, "第#{utils.numToZh(i)}周"]

    #星期代码
    $scope.days = []
    for i in [1...7]
        $scope.days.push [i, "星期#{utils.numToZh(i)}"]
    $scope.days.push [7, '星期日']

    #开始节数
    $scope.beginSessions = []
    for i in [1..5]
        $scope.beginSessions.push [i, "#{utils.numToZh(i * 2 -1)}#{utils.numToZh(i * 2)}节"]

    #结束节数
    $scope.endSessions = []
    for i in [1..5]
        $scope.endSessions.push [i, "至#{utils.numToZh(i * 2 -1)}#{utils.numToZh(i * 2)}节"]

    #获取当前时间日期
    date   = new Date()
    week   = $rootScope.user.week || 0
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
        $scope.loading = true
        request.query
            params:
                build: $scope.build
                week : $scope.week
                day  : $scope.day
                beginSession: $scope.beginSession
                endSession  : $scope.endSession
        , (error, info, data) ->
            $scope.loading = false
            $scope.error   = error
            $scope.data    = data

#选课平台
electiveConller = ($scope, request) ->
    $('.tabular .item').tab()

    #个人信息
    $scope.getPerson = ->
        $scope.person = loading:true
        request.query {}, (error, info, data) ->
            $scope.person.error    = error
            $scope.person.selected = data?.selected
            $scope.person.queue    = data?.queue
            $scope.person.loading  = false

    #选/退
    $scope.action = (title, url) ->
        if !confirm("您确定要#{title}吗？") then return
        request.query
            params:
                type : 'addQueue'
                title: title
                url  : url
        , (error, info, data) ->
            if angular.isObject(data) and !angular.isArray(data)
                $scope.person.queue.push data

    #选课列表
    $scope.search = (key, page) ->
        if key then $scope.key = key
        $scope.list = loading:true
        request.query
            params:
                type : 'search'
                key  : $scope.key
                page : page || 1
        , (error, info, data) ->
            $scope.list.error = error
            $scope.list.info  = info
            $scope.list.data  = data
            $scope.list.loading = false

    $scope.getPerson()
    $scope.search()

#教学评价
judgeController = ($scope, request) ->
    #获取评教列表
    $scope.list = ->
        $scope.loading = true
        request.query {}, (error, info, data) ->
            $scope.loading = false
            $scope.error   = error
            $scope.info    = info
            $scope.data    = data
    $scope.list()

    #自动评教
    $scope.auto =
        loading: false
        success: 0
        error  : 0
        random : ->
            rand = Math.random()
            if rand <= 0.2 then 1 else 0
        radio  : ->
            self = this
            radio = (this.random(x) for x in [0...10])
            if _.max(radio) is _.min(radio)
                self.radio()
            else
                radio
        action : ->
            self = this
            self.data = _.filter $scope.data, (item) ->
                parseInt(item.mark) < 55
            self.success = self.error = 0
            if self.data.length is 0
                return layer.msg '评教已经完成，无需再自动评教'
            self.loading = true
            for item in self.data
                request.query
                    data:
                        type  : 'submit'
                        radio : self.radio()
                        params: item.params
                , (error, info, data) ->
                    if error
                        self.error++
                    else
                        self.success++
                    if self.error + self.success is self.data.length
                        layer.msg "自动评教已完成，成功#{self.success}门，失败#{self.error}门"
                        self.loading = false
                        $scope.list()

    #评教
    $scope.judge = (item) ->
        $('.ui.checkbox').checkbox()
        $('.ui.judging.form').form 'clear'
        $scope.judging = item

    #提交评价
    $scope.submit = ->
        #检查数据
        radio = []
        flag = true
        for i in [0...10]
            radio[i] = $("input[name='radio#{i}']:checked").val()
            if !radio[i]
                layer.msg '请确定表单已填写完整。', shift:6
                return false
            if i isnt 0 and radio[i] isnt radio[i - 1]
                flag = false
        if flag
            layer.msg '不能全部选择相同的选项。', shift:6
            return false

        #提交
        $scope.judging.error = ''
        $scope.judging.loading = true
        request.query
            data:
                type  : 'submit'
                radio : radio
                params: $scope.judging.params
        , (error, info, data) ->
            if error
                layer.msg error
                $scope.judging.loading = false
            else
                $scope.judging = false
                $scope.list()

#成绩排名
rankController = ($scope, $timeout, $filter, request) ->
    $scope.error   = ''
    $scope.loading = true
    request.query {}, (error, info, data) ->
        $scope.loading = false
        $scope.error   = error
        $scope.info    = info
        $scope.data    = $filter('sortBy')(data, 'sid', true)
        $scope.init()

    $scope.init = ->
        $timeout ->
            $('.term.dropdown').dropdown
                transition: 'drop'
            $('.scope.dropdown').dropdown
                transition: 'drop'

    $scope.next = (index) ->
        $scope.info.sid = $scope.data[index + 1]?.sid || $scope.data[0].sid
        $scope.init()

#图书借阅
bookController = ($scope, $timeout, request) ->
    $('.tabular .item').tab()
    #回车键Submit
    $('.ui.form').form {},
        onSuccess: ->
            $scope.$apply ->
                $scope.search()

    $scope.person = loading:true
    request.query {}, (error, info, data) ->
        $scope.person.loading = false
        $scope.person.error   = error
        $scope.person.data    = data

    #续借
    $scope.renew = (item) ->
        $scope.person.loading = true
        item.type = 'renew'
        request.query
            data:item
        , (error, info, data) ->
            $scope.person.loading = false
            if data.indexOf('应还日期:') isnt -1
                item.time = data.substr(-10)

    #搜索书列表
    $scope.list =
        data   : []
        page   : 1
        error  : null
        loading: false
        nextLoading:false
    $scope.search = (key, page) ->
        #判断当前是否正在加载
        if $scope.list.loading or $scope.list.nextLoading then return
        #判断关键词
        $scope.key = key || $scope.key || ''
        if !$scope.key.length then return
        #判断页码
        page = if page and page > 0 then parseInt(page) else 1
        #加载中
        if page is 1
            $scope.list.data = []
            $scope.list.loading = true
        else
            $scope.list.nextLoading = true
        $scope.list.nextMsg = '正在加载数据...'

        request.query
            params:
                type: 'search'
                key : $scope.key
                page: page
            timeout: 20000
        , (error, info, data) ->
            $scope.list.loading = false
            $scope.list.nextLoading = false
            $scope.list.error = error
            $scope.list.page  = parseInt(info?.page) + 1
            $scope.list.data  = $scope.list.data.concat data
            if error
                $scope.list.error = error
            else if !$scope.list.data.length
                $scope.list.error = '未找到相关书籍'
            else if !data.length
                $scope.list.nextMsg = '没有更多了...'
            else
                $scope.list.nextMsg = '点击加载更多'
            $timeout ->
                $('.ui.accordion').accordion
                    duration: 200
                    exclusive: false

    #查找详细信息
    $scope.info = (item) ->
        if item.data or item.loading
            return
        item.loading = true
        request.query
            params:
                type:'info'
                id  :item.id
        , (error, info, data) ->
            item.loading = false
            item.data = data

#学费
tuitionController = ($scope, $timeout, request) ->
    $scope.error   = ''
    $scope.loading = true
    request.query {}, (error, info, data) ->
        $scope.loading = false
        $scope.error   = error
        if error then return
        $scope.info    = info
        $scope.total   = data.total
        delete data.total
        $scope.data    = data
        $scope.terms   = (k for k,v of $scope.data).sort (a, b) ->
            a < b

        $timeout ->
            $('.ui.positive.message').popup
                popup: $('.ui.flowing.popup')
                on   : 'hover'

#校园一卡通
cardController = ($scope, request) ->
    $scope.error   = ''
    $scope.loading = true
    request.query {}, (error, info, data) ->
        $scope.loading = false
        $scope.error   = error
        $scope.info    = info
        $scope.data    = data

    #挂失与解挂
    $scope.card = (type) ->
        msg = '您确定要' + if type is 'loss' then '挂失' else '解挂' + '吗？';
        if !confirm(msg)
            return
        $scope.loading = true
        request.query
            data:
                type: type
        , (error, info, data) ->
            $scope.loading = false
            $scope.info = info

#挂科率统计
failRateController = ($scope, $rootScope, $timeout, $filter, request) ->
    $scope.data =
        per   : 30
        page  : 1
        data  : []
        result: []
        action: (page)->
            if this.data.lenght is 0 then return
            this.page   = page || this.page
            this.total  = this.data.length
            this.offset = (this.page - 1) * this.per
            this.result = $filter('cut')(this.data, this.offset, this.offset + this.per)
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

    #搜索
    $scope.search = (key) ->
        if key then $scope.key = key
        if !$scope.key then return
        #请求服务器数据
        $scope.error   = ''
        $scope.loading = true
        request.query
            params:
                key:$scope.key
        , (error, info, data) ->
            $scope.loading   = false
            $scope.error     = error
            $scope.data.page = 1
            $scope.data.data = $filter('sortBy')(data, 'rate', false)
            $scope.data.action()

    #查自己学院挂科率
    if $rootScope.user.college
        $scope.search $rootScope.user.college

#挂科率统计
sexRatioController = ($scope, $location, request) ->
    #搜索
    $scope.search = (key) ->
        if key then $scope.key = key
        if !$scope.key then return
        #请求服务器数据
        $scope.error   = ''
        $scope.loading = true
        request.query
            params:
                key:$scope.key
        , (error, info, data) ->
            $scope.loading = false
            $scope.error   = error
            $scope.data    = data
    $scope.key = $location.search().key || '湖南科技大学'
    $scope.search()

#切片
cutFilter = ->
    (object, start, end) ->
        object.slice(start || 0, end || object.length)

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
hnust.controller 'navbar'    , navbarController
hnust.controller 'login'     , loginController
hnust.controller 'user'      , userController
hnust.controller 'api'       , apiController
hnust.controller 'score'     , scoreController
hnust.controller 'scoreAll'  , scoreAllController
hnust.controller 'schedule'  , scheduleController
hnust.controller 'exam'      , examController
hnust.controller 'credit'    , creditController
hnust.controller 'classroom' , classroomConller
hnust.controller 'elective'  , electiveConller
hnust.controller 'judge'     , judgeController
hnust.controller 'rank'      , rankController
hnust.controller 'book'      , bookController
hnust.controller 'tuition'   , tuitionController
hnust.controller 'card'      , cardController
hnust.controller 'failRate'  , failRateController
hnust.controller 'sexRatio'  , sexRatioController
hnust.filter     'cut'       , cutFilter
hnust.filter     'sortBy'    , sortByFilter