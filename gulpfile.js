/* globals require */
var gulp = require('gulp');
var watch = require('gulp-watch');
var sort = require('gulp-sort');
var uglify = require('gulp-uglify');
var rename = require('gulp-rename');
var pump = require('pump');
var cleanCSS = require('gulp-clean-css');
var wpPot = require('gulp-wp-pot');

var cssFiles = [
	'assets/css/klarna-checkout-for-woocommerce.css',
	'src/OrderManagement/assets/css/klarna-order-management.css'
];
var jsFiles = [
	'assets/js/klarna-checkout-for-woocommerce.js',
	'assets/js/klarna-checkout-for-woocommerce-admin.js',
	'src/OrderManagement/assets/js/klarna-order-management.js'
];
var translateFiles = '**/*.php';

gulp.task('makePOT', function () {
	return gulp.src('**/*.php')
		.pipe(sort())
		.pipe(wpPot({
			domain: 'klarna-checkout-for-woocommerce',
			destFile: 'languages/klarna-checkout-for-woocommerce.pot',
			package: 'klarna-checkout-for-woocommerce',
			bugReport: 'http://krokedil.se',
			lastTranslator: 'Krokedil <info@krokedil.se>',
			team: 'Krokedil <info@krokedil.se>'
		}))
		.pipe(gulp.dest('languages/klarna-checkout-for-woocommerce.pot'));
});

function makePot() {
	return gulp.src('**/*.php')
	.pipe(sort())
	.pipe(wpPot({
		domain: 'klarna-checkout-for-woocommerce',
		destFile: 'languages/klarna-checkout-for-woocommerce.pot',
		package: 'klarna-checkout-for-woocommerce',
		bugReport: 'http://krokedil.se',
		lastTranslator: 'Krokedil <info@krokedil.se>',
		team: 'Krokedil <info@krokedil.se>'
	}))
	.pipe(gulp.dest('languages/klarna-checkout-for-woocommerce.pot'));
}

gulp.task('CSS', function() {
    return gulp
	.src(cssFiles, { base: '.' })
        .pipe(cleanCSS({ debug: true }))
        .pipe(rename({ suffix: '.min' }))
	.pipe(gulp.dest('.'));
});

function css() {
	return gulp
	.src(cssFiles, { base: '.' })
	.pipe(cleanCSS({ debug: true }))
	.pipe(rename({ suffix: '.min' }))
	.pipe(gulp.dest('.'));
}

gulp.task('JS', function(cb) {
    pump([gulp.src(jsFiles, { base: '.' }), uglify(), rename({ suffix: '.min' }), gulp.dest('.')], cb);
});

function js(cb){
	pump([gulp.src(jsFiles, { base: '.' }), uglify(), rename({ suffix: '.min' }), gulp.dest('.')], cb);
}

gulp.task('watch', function() {
    gulp.watch(cssFiles, css);
    gulp.watch(jsFiles, js);
    gulp.watch(translateFiles, makePot);
});