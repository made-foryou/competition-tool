<?php

namespace App\Console\Commands;

use App\Models\Invitation;
use App\Models\User;
use App\Notifications\InvitationNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class InviteUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:invite {email : Het e-mailadres van de uit te nodigen gebruiker}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Nodig een nieuwe gebruiker uit voor de admin-console via e-mail';

    /**
     * Hoe lang een uitnodiging geldig blijft, in dagen.
     */
    protected int $expiresAfterDays = 7;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = (string) $this->argument('email');

        $validator = Validator::make(['email' => $email], [
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        if ($validator->fails()) {
            $this->error($validator->errors()->first('email'));

            return self::FAILURE;
        }

        if (User::query()->where('email', $email)->exists()) {
            $this->error("Er bestaat al een gebruiker met het e-mailadres [{$email}].");

            return self::FAILURE;
        }

        Invitation::query()->where('email', $email)->whereNull('accepted_at')->delete();

        $plainToken = Str::random(64);

        $invitation = Invitation::create([
            'email' => $email,
            'token' => hash('sha256', $plainToken),
            'expires_at' => now()->addDays($this->expiresAfterDays),
        ]);

        Notification::route('mail', $email)
            ->notify(new InvitationNotification($invitation, $plainToken));

        $this->info("Uitnodiging verstuurd naar [{$email}].");
        $this->line('Accept-link: '.route('invitation.show', $plainToken));

        return self::SUCCESS;
    }
}
