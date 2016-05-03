gulp = require('gulp')
$    = require('gulp-load-plugins')()

ngModule = 'hnust'

# 定义源文件
src =
    vendor: 'src/vendor/**/*'
    enter : 'src/*.html'
    css   : 'src/css/*'
    img   : 'src/img/*'
    ijs   : [
        'src/js/index.coffee'
        'src/js/paging.js'
    ]
    itpl  : [
        'src/tpl/index-*.*'
        'src/tpl/loading.html'
    ]
    ajs   : [
        'src/js/admin.coffee'
        'src/js/wordcloud2.js'
        'src/js/paging.js'
    ]
    atpl  : [
        'src/tpl/admin-*.*'
        'src/tpl/loading.html'
    ]

# 定义输出文件
dist =
    vendor: '../public/dist/vendor/'
    enter : '../public/dist/'
    js    : '../public/dist/js/'
    css   : '../public/dist/css/'
    img   : '../public/dist/img/'
    rev   : '../public/dist/rev/'

# 复制公共库
gulp.task 'vendor', ->
    gulp.src src.vendor
    .pipe gulp.dest dist.vendor

# 图片处理
gulp.task 'img', ->
    gulp.src src.img
    .pipe $.if '!favicon.ico', $.rev()
    .pipe gulp.dest dist.img
    .pipe $.rev.manifest()
    .pipe gulp.dest dist.rev + 'img'

# CSS处理
gulp.task 'css', ['img'], ->
    gulp.src src.css
    .pipe $.concat 'all.min.css'
    .pipe $.cleanCss()
    .pipe $.rev()
    .pipe gulp.dest dist.css
    .pipe $.rev.manifest()
    .pipe gulp.dest dist.rev + 'css'

# 处理JS
js = (src, dist, name) ->
    tplFilter = $.filter ['**/*.html'], restore: true
    jsFilter  = $.filter ['**/*.js','**/*.coffee'], restore: true

    gulp.src src
    # 处理模板文件为JS
    .pipe $.revCollector()
    .pipe tplFilter
    .pipe $.htmlMinify()
    .pipe $.angularTemplatecache
        module:ngModule
    .pipe tplFilter.restore
    # 处理并合并JS文件
    .pipe jsFilter
    .pipe $.if '*.coffee', $.coffee()
    .pipe $.ngAnnotate()
    .pipe $.ngmin dynamic: false
    # .pipe $.stripDebug()
    .pipe $.concat name + '.min.js'
    .pipe $.uglify outSourceMap: false
    .pipe $.rev()
    # 输出
    .pipe gulp.dest dist.js
    .pipe $.rev.manifest()
    .pipe gulp.dest dist.rev + name

# ijs处理
gulp.task 'ijs', ['img'], ->
    js src.ijs.concat(src.itpl).concat([
        dist.rev + '*/*'
    ]) , dist, 'ijs'

# ajs处理
gulp.task 'ajs', ['img'], ->
    js src.ajs.concat(src.atpl).concat([
        dist.rev + '*/*'
    ]) , dist, 'ajs'


# 首页文件
gulp.task 'enter' , ['img', 'ijs', 'ajs', 'css', 'vendor'], ->
    gulp.src [
        src.enter
        dist.rev + '*/*'
    ]
    .pipe $.revCollector()
    .pipe $.htmlMinify()
    .pipe gulp.dest dist.enter

# 清理文件
gulp.task 'default', ['enter'], ->
    gulp.src dist.rev, read: false
    .pipe $.clean force: true

gulp.task 'watch', ->
    gulp.watch src.vendor, ['vendor']
    gulp.watch [
        src.enter
        src.img
        src.css
        src.ijs
        src.itpl
        src.ajs
        src.atpl
    ], ['default']