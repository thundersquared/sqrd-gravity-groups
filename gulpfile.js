const { src, dest, series, task } = require('gulp')
const dels = require('del')
const replace = require('gulp-replace')
const zip = require('gulp-zip')
const watch = require('gulp-watch')
const project = require('./package')

function name() {
    let package = require('./package')
    return package.name
}

function file() {
    let package = require('./package')
    return `${package.name}-${package.version}.zip`
}

function version() {
    let package = require('./package')
    return package.version
}

function date() {
    return new Date().toJSON()
}

// Clean dist folder
function clean() {
    return dels([
        'build/*',
        'dist/*'
    ]);
}

// Build package replacing variables
function build() {
    return src('src/**')
        .pipe(replace('PLUGIN_VERSION', version()))
        .pipe(dest(`build/${name()}`))
}

// Package the project
function pack() {
    return src(`build/**`)
        .pipe(zip(file()))
        .pipe(dest('dist/'))
}

task('watch', function () {
    return watch('src/**', series(clean, build, pack))
})

// Export default task
exports.default = series(clean, build, pack)
