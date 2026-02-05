<?php

namespace Coderden\Comments\Console;

use Illuminate\Console\Command;

class InstallCommentsPackage extends Command
{
    protected $signature = 'comments:install';
    protected $description = 'Install the Comments package with all necessary files';
    
    public function handle()
    {
        $this->info('Installing Coderden Comments Package...');
        
        // Публикация конфигурации
        $this->call('vendor:publish', [
            '--tag' => 'comments-config',
            '--force' => true,
        ]);
        
        $this->info('✓ Configuration file published');
        
        // Публикация миграций
        $this->call('vendor:publish', [
            '--tag' => 'comments-migrations',
            '--force' => true,
        ]);
        
        $this->info('✓ Migration files published');
        
        // Запуск миграций
        if ($this->confirm('Run database migrations?', true)) {
            $this->call('migrate');
            $this->info('✓ Database migrations completed');
        }
        
        // Публикация ресурсов
        if ($this->confirm('Publish resource files?', false)) {
            $this->call('vendor:publish', [
                '--tag' => 'comments-resources',
                '--force' => true,
            ]);
            $this->info('✓ Resource files published');
        }
        
        $this->newLine();
        $this->info('✅ Comments package installed successfully!');
        $this->line('Next steps:');
        $this->line('1. Add the HasComments trait to your models');
        $this->line('2. Configure your comment settings in config/comments.php');
        $this->line('3. Add auth middleware to your routes if needed');
    }
}