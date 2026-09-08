<?php

namespace App\Console\Commands;

use App\Actions\Auth\SendInvitation;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

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

        $plainToken = app(SendInvitation::class)->handle($email, UserRole::Admin);

        $this->info("Uitnodiging verstuurd naar [{$email}].");
        $this->line('Accept-link: '.route('invitation.show', $plainToken));

        return self::SUCCESS;
    }
}
