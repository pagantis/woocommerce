module.exports = function(grunt) {
    grunt.initConfig({
        shell: {
            rename: {
                command:
                    'cp pagantis.zip pagantis-$(git rev-parse --abbrev-ref HEAD).zip \n'
            },
            composerProd: {
                command: 'composer install --no-dev'
            },
            composerDev: {
                command: 'composer install'
            },
        },
        compress: {
            main: {
                options: {
                    archive: 'pagantis.zip'
                },
                files: [
                    {src: ['assets/**'], dest: 'pagantis/', filter: 'isFile'},
                    {src: ['controllers/**'], dest: 'pagantis/', filter: 'isFile'},
                    {src: ['includes/**'], dest: 'pagantis/', filter: 'isFile'},
                    {src: ['languages/**'], dest: 'pagantis/', filter: 'isFile'},
                    {src: ['templates/**'], dest: 'pagantis/', filter: 'isFile'},
                    {src: ['vendor/**'], dest: 'pagantis/', filter: 'isFile'},
                    {src: 'WC_Pagantis.php', dest: 'pagantis/'},
                    {src: 'readme.txt', dest: 'pagantis/'}
                ]
            }
        }
    });

    grunt.loadNpmTasks('grunt-shell');
    grunt.loadNpmTasks('grunt-contrib-compress');
    grunt.registerTask('default', [
        'shell:composerProd',
        'compress',
        'shell:composerDev',
        'shell:rename'
    ]);
};
