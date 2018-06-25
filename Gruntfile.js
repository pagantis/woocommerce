module.exports = function(grunt) {
    grunt.initConfig({
        shell: {
            rename: {
                command:
                    'cp paylater.zip paylater-$(git rev-parse --abbrev-ref HEAD).zip \n'
            },
            composerProd: {
                command: 'rm -rf vendor && composer install --no-dev'
            },
            composerDev: {
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
                    {src: ['controllers/**'], dest: 'paylater/', filter: 'isFile'},
                    {src: ['includes/**'], dest: 'paylater/', filter: 'isFile'},
                    {src: ['languages/**'], dest: 'paylater/', filter: 'isFile'},
                    {src: ['templates/**'], dest: 'paylater/', filter: 'isFile'},
                    {src: ['vendor/**'], dest: 'paylater/', filter: 'isFile'},
                    {src: 'WC_Paylater.php', dest: 'paylater/'},
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
