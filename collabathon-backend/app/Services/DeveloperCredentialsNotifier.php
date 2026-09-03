<?php

namespace App\Services;

use App\Mail\DeveloperCredentialsMail;
use App\Models\User;
use App\Support\MailSettings;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Sends a newly-created developer their sign-in details over email and WhatsApp — the
 * one piece of work shared identically by DeveloperController::store() (created by hand
 * in the admin panel) and MasterDataController::convert() (created from an external
 * registration). Both need the exact same delivery + "what actually went out" summary,
 * so it lives here once rather than twice.
 */
class DeveloperCredentialsNotifier
{
    public function __construct(private WhatsAppCredentialsSender $whatsApp)
    {
    }

    /**
     * @return array{emailed:bool, whatsapped:bool, note:string} `note` is a ready-to-
     *         append sentence for a flash message — blank when neither channel sent.
     */
    public function send(User $user, string $password, string $recipientName): array
    {
        $emailed = $this->email($user, $password);
        $whatsapped = $this->whatsApp->send($user->mobile, $user->name, $user->email, $password);

        $delivered = array_filter([
            $emailed ? 'emailed' : null,
            $whatsapped ? 'sent on WhatsApp' : null,
        ]);

        return [
            'emailed' => $emailed,
            'whatsapped' => $whatsapped,
            'note' => $delivered
                ? ' Credentials ' . implode(' and ', $delivered) . " to {$recipientName}."
                : '',
        ];
    }

    /**
     * Every failure path is swallowed on purpose — the account is already created and
     * that must not surface as a 500 on a page whose work succeeded.
     */
    private function email(User $user, string $password): bool
    {
        if (! MailSettings::apply()) {
            return false;
        }

        try {
            Mail::to($user->email)->send(new DeveloperCredentialsMail($user, $password));

            return true;
        } catch (\Throwable $e) {
            Log::error('Developer credentials email failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
