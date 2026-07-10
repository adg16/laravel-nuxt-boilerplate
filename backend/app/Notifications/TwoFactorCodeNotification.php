<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Emails a one-time 2FA code. Queued (like invitations and password resets) so a
 * transport hiccup surfaces in the worker, not as a request 500 — the queue
 * worker must be running for the code to arrive. Built as a MailMessage so it
 * inherits the app's published brand mail theme.
 */
class TwoFactorCodeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $code) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('two_factor.subject', ['app' => config('app.name')]))
            ->greeting(__('two_factor.greeting', ['name' => $notifiable->name]))
            ->line(__('two_factor.intro'))
            ->line(__('two_factor.code', ['code' => $this->code]))
            ->line(__('two_factor.expiry'))
            ->line(__('two_factor.ignore'));
    }
}
