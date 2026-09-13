<?php

namespace App\Notifications;

use App\Models\Competition;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Herinnering aan een deelnemer die zijn beschikbaarheid nog niet heeft
 * ingediend. Wordt alleen verstuurd door de beheerder naar deelnemers zonder
 * `availability_submitted_at` op de koppeltabel, nooit naar wie al heeft
 * ingevuld.
 */
class AvailabilityReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Competition $competition,
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
            ->subject(__('Reminder: fill in your availability for :competition', ['competition' => $this->competition->name]))
            ->line(__('We still need your availability for :competition to build the match schedule.', ['competition' => $this->competition->name]))
            ->action(__('Fill in availability'), route('competition.availability.edit', $this->competition))
            ->line(__('If you already submitted your availability, you can ignore this email.'));
    }
}
