<?php

namespace App\Console\Commands;

use App\Models\DeviceToken;
use App\Models\User;
use App\Services\Fcm;
use Illuminate\Console\Command;

/**
 * Verifies push works *on the machine it is run on*.
 *
 * Exists because every part of the push chain that can break on deploy breaks silently:
 * the service account is gitignored so it does not ship with the code, the storage path
 * differs per host, the file may land unreadable by the web user, and outbound HTTPS to
 * Google is blocked on plenty of shared hosts. None of those surface as an error — they
 * surface as notifications that simply never arrive.
 *
 *   php artisan fcm:check                 # credentials + reach Google
 *   php artisan fcm:check --user=11       # …and actually send to that user's devices
 */
class FcmCheck extends Command
{
    protected $signature = 'fcm:check {--user= : Send a real test notification to this user id}';

    protected $description = 'Check that push notifications can be sent from this server';

    public function handle(Fcm $fcm): int
    {
        $path = storage_path(config('services.fcm.credentials'));

        $this->line('');
        $this->line('  Credentials path: ' . $path);

        if (! file_exists($path)) {
            $this->error('  ✗ File not found. Upload the service account JSON to that path.');

            return self::FAILURE;
        }

        if (! is_readable($path)) {
            $this->error('  ✗ File exists but is not readable by ' . (get_current_user() ?: 'this user') . '.');
            $this->line('    The web server runs as its own user — chown it to that one.');

            return self::FAILURE;
        }

        if (! $fcm->configured()) {
            $this->error('  ✗ File is readable but is not a valid service account JSON.');
            $this->line('    It needs project_id, client_email and private_key.');

            return self::FAILURE;
        }

        $this->info('  ✓ Service account readable and well-formed');

        // A syntactically valid but non-existent token. Reaching FCM at all proves the
        // OAuth2 exchange worked and outbound HTTPS is open; FCM then rejects the token,
        // which is the expected outcome and not a failure of this check.
        $result = $fcm->send(['fcm-check-probe-token'], 'Probe', 'Connectivity check');

        if ($result['invalid'] === [] && $result['sent'] === 0) {
            $this->error('  ✗ Could not reach FCM. Check outbound HTTPS to oauth2.googleapis.com');
            $this->line('    and fcm.googleapis.com, then read storage/logs for the reason.');

            return self::FAILURE;
        }

        $this->info('  ✓ Authenticated with Google and reached FCM');

        $userId = $this->option('user');
        if ($userId === null) {
            $this->line('');
            $this->line('  Pass --user=<id> to send a real notification to a signed-in device.');
            $this->line('');

            return self::SUCCESS;
        }

        $user = User::find($userId);
        if ($user === null) {
            $this->error("  ✗ No user with id {$userId}.");

            return self::FAILURE;
        }

        $tokens = DeviceToken::where('user_id', $user->id)->pluck('token')->all();
        if ($tokens === []) {
            $this->warn("  ! {$user->name} has no registered devices — sign in on the app first.");

            return self::SUCCESS;
        }

        $sent = $fcm->send(
            $tokens,
            'Collabathon',
            'Push notifications are working.',
            ['type' => 'announcement'],
        );

        $this->line('');
        $this->info(sprintf(
            '  Sent to %d of %d device%s for %s.',
            $sent['sent'], count($tokens), count($tokens) === 1 ? '' : 's', $user->name,
        ));

        if ($sent['invalid'] !== []) {
            DeviceToken::whereIn('token', $sent['invalid'])->delete();
            $this->warn('  ' . count($sent['invalid']) . ' dead token(s) pruned.');
        }

        $this->line('');

        return self::SUCCESS;
    }
}
