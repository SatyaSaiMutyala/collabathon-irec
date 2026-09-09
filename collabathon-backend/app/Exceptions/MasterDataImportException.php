<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A Master Data registration that cannot be imported.
 *
 * Every message here is written for the admin who will read it on the page, because
 * the data behind it belongs to irecexpo.com and nobody on this side can fix it by
 * editing code — the only useful response is to say precisely what is missing or
 * clashing and what to do about it. Named constructors rather than raw `new`, so the
 * wording for a given failure lives in one place instead of being retyped at each
 * throw site.
 */
class MasterDataImportException extends RuntimeException
{
    /** A field the import cannot proceed without is empty on the vendor's side. */
    public static function missingField(string $label): self
    {
        return new self(
            "This registration has no {$label}, which is required to create a listing. "
            .'Ask the developer to complete it on irecexpo.com, then convert again.'
        );
    }

    /**
     * The email belongs to a broker or an admin. Reusing it would hand one person two
     * roles on the same login, so this is the one collision the import will not resolve
     * on its own.
     */
    public static function emailBelongsToAnotherRole(string $email, string $role): self
    {
        return new self(
            "{$email} is already in use by a {$role} account. "
            .'An email can belong to only one account, so free it up or correct the registration before converting.'
        );
    }

    /** Same reasoning as the email above, for the mobile number. */
    public static function mobileBelongsToAnotherRole(string $mobile, string $role): self
    {
        return new self(
            "{$mobile} is already in use by a {$role} account. "
            .'Free it up or correct the registration before converting.'
        );
    }

    /**
     * The company is in Trash. Restoring it and importing again is one click and keeps
     * the developer's existing listings and history attached; creating a second account
     * around the deleted one's email is not even possible, and would be the wrong answer
     * if it were.
     */
    public static function developerInTrash(string $company): self
    {
        return new self(
            "\"{$company}\" is in Trash. Restore the developer from there and convert this registration again "
            .'to add the listing to their existing account.'
        );
    }

    /**
     * A developer login exists but has no company record behind it — a half-created
     * account that the import must not build on, since the listing needs a developer row
     * to hang off.
     */
    public static function developerAccountIncomplete(string $email): self
    {
        return new self(
            "The account for {$email} exists but has no developer profile attached. "
            .'Open Developers and finish setting that account up, then convert again.'
        );
    }

    /**
     * A listing carries this registration's reference code but its developer cannot be
     * found even among the soft-deleted. The foreign key makes this close to impossible,
     * so it is here to fail loudly rather than let the import run on and collide with
     * the reference code's unique index as a 500.
     */
    public static function orphanedListing(string $reference): self
    {
        return new self(
            "Registration {$reference} is already linked to a listing whose developer is missing. "
            .'That needs looking at in the database before this registration can be imported again.'
        );
    }

}
