<?php

namespace App\Services\MasterData;

use App\Models\Developer;
use App\Models\Property;

/**
 * What one call to {@see MasterDataImporter::import()} actually did.
 *
 * The controller needs to tell four situations apart — a brand-new developer with
 * their first listing, an existing developer gaining another, a re-run of a
 * registration already imported, and each of those with or without a fresh login to
 * hand over — and a bare Property tells it none of them. Returning this instead keeps
 * that decision out of the importer, which has no business writing flash messages.
 */
readonly class ImportOutcome
{
    /**
     * @param  array{name: string, email: string, password: string}|null  $credentials
     *   The generated login, present only when this import created the account. Shown
     *   once in the credentials dialog and never retrievable again, since the password
     *   is stored hashed.
     * @param  string  $deliveryNote  Where those credentials were sent, ready to append
     *   to the success message. Empty when nothing was sent or nothing was created.
     * @param  list<string>  $catalogueAdditions  Master-data entries this import added
     *   because the vendor used a word we had not seen — see {@see MasterDataCatalogue}.
     *   Surfaced rather than silent: an entry created from a mistyped field is only
     *   fixable by an admin who knows it appeared.
     */
    public function __construct(
        public Developer $developer,
        public Property $property,
        public bool $developerCreated,
        public bool $propertyCreated,
        public ?array $credentials = null,
        public string $deliveryNote = '',
        public array $catalogueAdditions = [],
    ) {}

    /** A registration converted before: nothing was written this time. */
    public static function alreadyImported(Developer $developer, Property $property): self
    {
        return new self($developer, $property, developerCreated: false, propertyCreated: false);
    }
}
