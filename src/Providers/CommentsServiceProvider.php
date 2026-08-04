<?php

namespace Coderden\Comments\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Gate;
use Coderden\Comments\Console\InstallCommentsPackage;
use Coderden\Comments\Console\CleanupOldComments;
use Coderden\Comments\Services\CommentService;
use Coderden\Comments\Models\Comment;
use Coderden\Comments\Policies\CommentPolicy;

class CommentsServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../Config/comments.php', 'comments'
        );

        $this->app->singleton('comments', function ($app) {
            return new CommentService();
        });

        $this->app->alias('comments', CommentService::class);
    }

    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->registerRoutes();
        $this->registerPublishing();
        $this->registerCommands();
        $this->registerEvents();
        $this->registerPolicies();
        $this->registerMiddleware();
    }

    protected function registerRoutes()
    {
        Route::group([
            'prefix' => config('comments.route.prefix', 'api/comments'),
            'namespace' => 'Coderden\Comments\Http\Controllers',
            'middleware' => config('comments.route.middleware', ['api']),
        ], function () {
            $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        });

         Route::group([
            'prefix' => 'api/comments/light',
            'namespace' => 'Coderden\Comments\Http\Controllers',
            'middleware' => ['api'],
        ], function () {
            $this->loadRoutesFrom(__DIR__.'/../routes/light-api.php');
        });
    }

    protected function registerPublishing()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../Config/comments.php' => config_path('comments.php'),
            ], 'comments-config');

            $this->publishes([
                __DIR__.'/../Database/Migrations' => database_path('migrations'),
            ], 'comments-migrations');

            $this->publishes([
                __DIR__.'/../Resources' => app_path('Http/Resources/Comments'),
            ], 'comments-resources');

            $this->publishes([
                __DIR__.'/../Policies/CommentPolicy.php' => app_path('Policies/CommentPolicy.php'),
            ], 'comments-policies');
        }
    }

    protected function registerCommands()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommentsPackage::class,
            ]);
        }
    }

    protected function registerEvents()
    {
        $events = config('comments.events', []);
        
        foreach ($events as $event => $listeners) {
            foreach ($listeners as $listener) {
                \Illuminate\Support\Facades\Event::listen($event, $listener);
            }
        }
    }

    protected function registerPolicies()
    {
        Gate::policy(Comment::class, CommentPolicy::class);
    }

    protected function registerMiddleware()
    {
        $router = $this->app['router'];
        $router->aliasMiddleware('comment.limits', \Coderden\Comments\Http\Middleware\CheckCommentLimits::class);
    }
}