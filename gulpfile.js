/* globals require */
var gulp = require('gulp');
var watch = require('gulp-watch');
var sort = require('gulp-sort');
var uglify = require('gulp-uglify');
var rename = require('gulp-rename');
var pump = require('pump');
var cleanCSS = require('gulp-clean-css');

var cssFile = 'assets/css/klarna-checkout-for-woocommerce.css';
var jsFile = 'assets/js/klarna-checkout-for-woocommerce.js';

gulp.task('CSS', function() {
    return gulp
        .src(cssFile)
        .pipe(cleanCSS({ debug: true }))
        .pipe(rename({ suffix: '.min' }))
        .pipe(gulp.dest('assets/css'));
});

gulp.task('JS', function(cb) {
    pump([gulp.src(jsFile), uglify(), rename({ suffix: '.min' }), gulp.dest('assets/js')], cb);
});

gulp.task('watch', function() {
    gulp.watch(cssFile, ['CSS']);
    gulp.watch(jsFile, ['JS']);
});
