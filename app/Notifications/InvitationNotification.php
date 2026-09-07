<?php

namespace App\Notifications;

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvitationNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Invitation $invitation,
        public string $plainToken,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__("You're invited to :app", ['app' => config('app.name')]))
            ->line(__('You have been invited to the :app admin console.', ['app' => config('app.name')]))
            ->action(__('Accept invitation'), route('invitation.show', $this->plainToken))
            ->line(__('This invitation is valid until :date.', [
                'date' => $this->invitation->expires_at->translatedFormat('j F Y H:i'),
            ]))
            ->line(__('If you did not expect this invitation, you can ignore this email.'));
    }
}
