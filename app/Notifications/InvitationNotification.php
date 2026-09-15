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
     * @param  bool  $hasAccount  of het uitgenodigde e-mailadres al een account heeft
     */
    public function __construct(
        public Invitation $invitation,
        public string $plainToken,
        public bool $hasAccount = false,
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
        $competition = $this->invitation->competition;

        $mail = (new MailMessage)
            ->subject(__("You're invited to :app", ['app' => config('app.name')]));

        if ($competition !== null) {
            $mail->line(__('You have been invited to join the competition :competition.', [
                'competition' => $competition->name,
            ]));
        } else {
            $mail->line(__('You have been invited to the :app admin console.', ['app' => config('app.name')]));
        }

        // Wie al een account heeft hoeft er geen aan te maken; voor hem is de
        // link een inlog-en-aanmelden-knop. De bestemming blijft in beide
        // gevallen de uitnodigingspagina: die kent alle staten (afgerond, nog
        // niet geopend, al gebruikt) en kan uitleggen wat er aan de hand is.
        if ($this->hasAccount) {
            $mail->line(__('You already have an account for :email, so you only have to log in.', [
                'email' => $this->invitation->email,
            ]));
        }

        return $mail
            ->action(
                $this->hasAccount ? __('Log in and sign up') : __('Accept invitation'),
                route('invitation.show', $this->plainToken),
            )
            ->line(__('This invitation is valid until :date.', [
                'date' => $this->invitation->expires_at->translatedFormat('j F Y H:i'),
            ]))
            ->line(__('If you did not expect this invitation, you can ignore this email.'));
    }
}
