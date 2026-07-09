<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * The framework's password-reset notification, but queued.
 *
 * Fortify sends the reset link synchronously inside the forgot-password
 * request. Queuing the mail instead means a transport hiccup surfaces in the
 * worker (already logged/retried there) rather than as a 500 on the request —
 * which would also leak, via the differing status code, that the address
 * exists. It also matches this app's mail philosophy (invitations are queued
 * too), so the queue worker must be running for reset mail to arrive.
 *
 * Extending the base class keeps the brand-themed MailMessage and the
 * SPA reset URL wired up in AppServiceProvider::boot (ResetPassword::createUrlUsing).
 */
class QueuedResetPassword extends ResetPassword implements ShouldQueue
{
    use Queueable;
}
