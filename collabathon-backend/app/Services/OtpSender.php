<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Delivers an OTP to a mobile number. No SMS gateway is configured on this project yet
 * — the same position `MAIL_MAILER=log` already takes for email — so this logs the
 * code instead of texting it, which is enough to build and test the whole sign-in flow
 * end to end without a paid account.
 *
 * `AuthController` depends on this class, not on `Log::info` directly, so wiring up a
 * real provider later (Twilio, MSG91, SNS, …) is "replace what's inside `send()`", not
 * a hunt through the controller.
 */
class OtpSender
{
    public function send(string $mobile, string $code): void
    {
        // In local/testing this is the only "delivery" there is — the code is also
        // echoed back in the API response by AuthController, gated the same way, so a
        // developer never has to open a log file mid-flow to keep testing.
        Log::info("OTP for {$mobile}: {$code}");
    }
}
