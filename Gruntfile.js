/* eslint-env node */
/* jshint node:true */

module.exports = function( grunt ) {
	'use strict';

	grunt.initConfig( {

		pkg: grunt.file.readJSON( 'package.json' ),

		// JavaScript linting with JSHint.
		jshint: {
			options: {
				jshintrc: '.jshintrc'
			},
			all: [
				'Gruntfile.js',
				'js/*.js',
				'!js/*.min.js'
			]
		},

		// Minify .js files.
		uglify: {
			options: {
				preserveComments: false
			},
			core: {
				files: [ {
					expand: true,
					cwd: 'js/',
					src: [
						'*.js',
						'!*.min.js'
					],
					dest: 'js/',
					ext: '.min.js'
				} ]
			}
		},

		// Minify .css files.
		cssmin: {
			core: {
				files: [ {
					expand: true,
					cwd: 'css/',
					src: [
						'*.css',
						'!*.min.css'
					],
					dest: 'css/',
					ext: '.min.css'
				} ]
			}
		},

		// Build a deploy-able plugin
		copy: {
			build: {
				src: [
					'*.php',
					'css/*',
					'js/*',
					'php/*',
					'readme.txt'
				],
				dest: 'build',
				expand: true,
				dot: true
			}
		},

		// Clean up the build
		clean: {
			build: {
				src: [ 'build' ]
			}
		},

		// VVV (Varying Vagrant Vagrants) Paths
		vvv: {
			'plugin': '/srv/www/wordpress-develop/src/wp-content/plugins/<%= pkg.name %>',
			'coverage': '/srv/www/default/coverage/<%= pkg.name %>'
		},

		// Shell actions
		shell: {
			options: {
				stdout: true,
				stderr: true
			},
			readme: {
				command: 'cd ./dev-lib && ./generate-markdown-readme' // Generate the readme.md
			},
			phpunit: {
				command: 'vagrant ssh -c "cd <%= vvv.plugin %> && phpunit"'
			},
			phpunit_c: {
				command: 'vagrant ssh -c "cd <%= vvv.plugin %> && phpunit --coverage-html <%= vvv.coverage %>"'
			}
		},

		// Deploys a git Repo to the WordPress SVN repo
		wp_deploy: {
			deploy: {
				options: {
					plugin_slug: '<%= pkg.name %>',
					build_dir: 'build',
					assets_dir: 'wp-assets'
				}
			}
		}

	} );

	// Load tasks
	grunt.loadNpmTasks( 'grunt-contrib-clean' );
	grunt.loadNpmTasks( 'grunt-contrib-copy' );
	grunt.loadNpmTasks( 'grunt-contrib-cssmin' );
	grunt.loadNpmTasks( 'grunt-contrib-jshint' );
	grunt.loadNpmTasks( 'grunt-contrib-uglify' );
	grunt.loadNpmTasks( 'grunt-shell' );
	grunt.loadNpmTasks( 'grunt-wp-deploy' );

	// Register tasks
	grunt.registerTask( 'default', [
		'jshint',
		'uglify',
		'cssmin'
	] );

	grunt.registerTask( 'readme', [
		'shell:readme'
	] );

	grunt.registerTask( 'phpunit', [
		'shell:phpunit'
	] );

	grunt.registerTask( 'phpunit_c', [
		'shell:phpunit_c'
	] );

	grunt.registerTask( 'dev', [
		'default',
		'readme'
	] );

	grunt.registerTask( 'build', [
		'default',
		'readme',
		'copy'
	] );

	grunt.registerTask( 'deploy', [
		'build',
		'wp_deploy',
		'clean'
	] );

};
