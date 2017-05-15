'use strict';
module.exports = function (grunt) {
    require('load-grunt-tasks')(grunt);
    require('time-grunt')(grunt);

    var jsFileList = [
        'bower_components/jquery.facedetection/dist/jquery.facedetection.js',
        'assets/js/main.js'
    ];

    grunt.initConfig({
        makepot: {
            options: {
                type: 'wp-plugin',
                domainPath: 'languages',
                potHeaders: {
                    'report-msgid-bugs-to': 'https://github.com/interconnectit/my-eyes-are-up-here/issues',
                    'language-team': 'LANGUAGE <EMAIL@ADDRESS>'
                }
            },
            dist: {
                options: {
                    potFilename: 'my-eyes-are-up-here.pot',
                    exclude: [
                        'assets/.*'
                    ]
                }
            }
        },
        checktextdomain: {
            options: {
                text_domain: 'my-eyes-are-up-here',
                keywords: [
                    '__:1,2d',
                    '_e:1,2d',
                    '_x:1,2c,3d',
                    'esc_html__:1,2d',
                    'esc_html_e:1,2d',
                    'esc_html_x:1,2c,3d',
                    'esc_attr__:1,2d',
                    'esc_attr_e:1,2d',
                    'esc_attr_x:1,2c,3d',
                    '_ex:1,2c,3d',
                    '_n:1,2,4d',
                    '_nx:1,2,4c,5d',
                    '_n_noop:1,2,3d',
                    '_nx_noop:1,2,3c,4d'
                ]
            },
            files: {
                src: [
                    '**/*.php',
                    '!assets/**',
                    '!bower_components/**',
                    '!node_modules/**',
                ],
                expand: true
            }
        },
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
        'makepot',
        'autoprefixer',
        'cssmin',
        'jshint',
        'uglify'
    ]);
};