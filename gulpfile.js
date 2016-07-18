var gulp = require('gulp');
var obt = require('origami-build-tools');

gulp.task('build', function() {
    obt.build.js(gulp, {js: './public/js/main.js', buildFolder: './public', env: 'production' });
    obt.build.sass(gulp, {sass: './public/scss/main.scss', buildFolder: './public', env: 'production'});
});

gulp.task('verify', function() {
    obt.verify(gulp, {
        js: './public/js/main.js',
        sass: './public/scss/main.scss'
    });
});

gulp.task('watch', function() {
	gulp.watch(['public/scss/**/*.scss','public/js/**/*.js'] , ['build']);
});
