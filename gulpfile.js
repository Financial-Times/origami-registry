var gulp = require('gulp');
var obt = require('origami-build-tools');

gulp.task('build', function() {
    obt.build.js(gulp, {js: './public/js/main.js', buildFolder: './public' });
    obt.build.sass(gulp, {sass: './public/scss/main.scss', buildFolder: './public'});
});

gulp.task('verify', function() {
    obt.verify(gulp, {
        js: './public/js/main.js',
        sass: './public/scss/main.scss'
    });
});
