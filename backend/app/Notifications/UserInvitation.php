<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The e-mail an invited user receives — dedicated invite messaging (not the
 * password-reset copy). Queued so a mail hiccup never fails the create-user
 * request that triggered it.
 */
class UserInvitation extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $token) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = rtrim(config('app.url'), '/').'/accept-invite?'.http_build_query([
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        $app = config('app.name');

        return (new MailMessage)
            ->subject(__('invitation.subject', ['app' => $app]))
            ->greeting(__('invitation.greeting', ['name' => $notifiable->name]))
            ->line(__('invitation.line1', ['app' => $app]))
            ->action(__('invitation.action'), $url)
            ->line(__('invitation.expires', ['days' => config('invitation.expire_days')]))
            ->line(__('invitation.ignore'));
    }
}
