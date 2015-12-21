'use strict';
module.exports = function (grunt) {
    require('load-grunt-tasks')(grunt);
    require('time-grunt')(grunt);

    var jsFileList = [
        'bower_components/jquery.facedetection/dist/jquery.facedetection.js',
        'assets/js/main.js'
    ];

    grunt.initConfig({
        autoprefixer: {
            options: {
                browsers: [
                    'last 2 versions',
                    'ie 8',
                    'ie 9',
                    'android 2.3',
                    'android 4',
                    'opera 12'
                ]
            },
            dist: {
                src: 'assets/css/main.min.css'
            }
        },
        cssmin: {
            options: {
                compatibility: 'ie8',
                keepSpecialComments: '*',
                noAdvanced: true
            },
            dist: {
                files: [{
                    expand: true,
                    cwd: 'assets/css',
                    src: ['*.css', '!*.min.css'],
                    dest: 'assets/css',
                    ext: '.min.css'
                }]
            }
        },
        jshint: {
            options: {
                jshintrc: '.jshintrc'
            },
            all: [
                'Gruntfile.js',
                'assets/js/*.js',
                '!assets/js/scripts.min.js'
            ]
        },
        uglify: {
            options: {
                preserveComments: 'some'
            },
            dist: {
                src: jsFileList,
                dest: 'assets/js/scripts.min.js'
            }
        },
    });

    grunt.registerTask('build', [
        'autoprefixer',
        'cssmin',
        'jshint',
        'uglify'
    ]);
};