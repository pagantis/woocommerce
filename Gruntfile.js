module.exports = function(grunt) {
    grunt.initConfig({
        shell: {
            __rename: {
                command:
                    'cp paylater.zip paylater-$(git rev-parse --abbrev-ref HEAD).zip \n'
            },
            __autoindex: {
                command:
                'php vendor/pagamastarde/autoindex/index.php . \n' +
                'rm -rf vendor/pagamastarde/autoindex \n'
            },
            composerProd: {
                command: 'rm -rf vendor && composer install --no-dev'
            },
            __composerDev: {
                command: 'composer install'
            },
        },
        compress: {
            main: {
                options: {
                    archive: 'paylater.zip'
                },
                files: [
                    {src: ['assets/**'], dest: 'paylater/', filter: 'isFile'},
                    {src: ['includes/**'], dest: 'paylater/', filter: 'isFile'},
                    {src: 'class-wc-paylater.php', dest: 'paylater/'},
                ]
            }
        }
    });

    grunt.loadNpmTasks('grunt-shell');
    grunt.loadNpmTasks('grunt-contrib-compress');
    grunt.registerTask('default', [
        'shell:composerProd',
        'compress',
    ]);

    //manually run the selenium test: "grunt shell:testPrestashop16"
    /*
     'shell:rename'
     'shell:composerDev'
     'shell:autoindex',
     'shell:composerDev',
    *
    *
    * */
};