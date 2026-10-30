<?php

namespace App\Providers;

use App\Models\User;
use App\Models\Candidate;
use App\Policies\CandidatePolicy;
use App\Policies\LeadDocumentPolicy;
use App\Models\LeadDocument;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Vite;
use Illuminate\Notifications\Messages\MailMessage;
use LogicException;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->environment('production') && config('app.debug')) {
            throw new LogicException('APP_DEBUG debe estar desactivado en producción.');
        }

        \Illuminate\Support\Facades\RateLimiter::for('agent-ip', fn ($request) => \Illuminate\Cache\RateLimiting\Limit::perMinute(240)->by($request->ip()));
        \Illuminate\Support\Facades\RateLimiter::for('agent-enroll', fn ($request) => \Illuminate\Cache\RateLimiting\Limit::perMinute(10)->by($request->ip()));
        \Illuminate\Support\Facades\RateLimiter::for('agent-station', fn ($request) => \Illuminate\Cache\RateLimiting\Limit::perMinute(120)->by('station:'.$request->attributes->get('agentStation')->id));

        VerifyEmail::toMailUsing(function (object $notifiable, string $verificationUrl): MailMessage {
            return (new MailMessage)
                ->subject('Confirma tu correo · The Velvet Studio')
                ->view('emails.verify-email', [
                    'verificationUrl' => $verificationUrl,
                    'recipientName' => $notifiable->name ?: 'Hola',
                    'expiresIn' => config('auth.verification.expire', 60),
                ]);
        });

        User::updated(function (User $user): void {
            if (! $user->wasChanged('is_active') || $user->is_active || config('session.driver') !== 'database' || ! Schema::hasTable(config('session.table'))) {
                return;
            }

            DB::table(config('session.table'))->where('user_id', $user->id)->delete();
        });

        Gate::before(fn (User $user, string $ability) => $user->hasPermissionTo($ability) ? true : null);
        Gate::policy(Candidate::class, CandidatePolicy::class);
        Gate::policy(LeadDocument::class, LeadDocumentPolicy::class);
        Vite::prefetch(concurrency: 3);
    }
}
