<?php

namespace App\Services;

use App\Models\Property;
use App\Support\FileStorage;
use Illuminate\Support\Collection;

/**
 * Removes a listing and every file it owns.
 *
 * The database half was always correct — property_details, property_unit_types,
 * property_media and leads are all `cascadeOnDelete` on properties.id, and properties
 * itself cascades from developers.id. The *files* those rows pointed at were the part
 * nobody deleted: a listing carries six separate sources of them, so deleting one used
 * to leave its brochure, gallery, floor plans and legal documents in the bucket forever,
 * with nothing left in the database to find them by.
 *
 * Exposed as two operations because deleting a developer needs only the file half —
 * the listing rows are already being cascaded away by the foreign key, so re-deleting
 * them here would be redundant work on a row that no longer exists.
 */
class PropertyDeleter
{
    /** Deletes one listing, then the files it owned. */
    public function delete(Property $property): string
    {
        $name = $property->name;
        $paths = $this->filePathsFor($property);

        $property->delete();

        // After the row is gone. A file removed first would leave a live listing
        // pointing at a missing image if the delete then failed.
        $this->deletePaths($paths);

        return $name;
    }

    /**
     * Every file path a listing owns, across all six places one can live.
     *
     * @return list<string>
     */
    public function filePathsFor(Property $property): array
    {
        $property->loadMissing(['detail', 'unitTypes', 'media']);

        return array_values(array_filter([
            $property->logo_path,
            $property->cover_image_path,
            $property->detail?->legal_due_diligence_path,
            $property->detail?->terms_document_path,
            // `path` is null on a media row that is an external `url` instead of an
            // upload — nothing of ours to delete in that case.
            ...$property->media->pluck('path')->all(),
            ...$property->unitTypes->pluck('floor_plan_path')->all(),
        ]));
    }

    /**
     * The file half alone, for callers whose rows are already being cascaded away.
     *
     * @param  Collection<int, Property>  $properties
     * @return list<string> the paths that were deleted
     */
    public function deleteFilesFor(Collection $properties): array
    {
        $paths = $properties
            ->flatMap(fn (Property $property) => $this->filePathsFor($property))
            ->all();

        $this->deletePaths($paths);

        return $paths;
    }

    /** @param  list<string>  $paths */
    private function deletePaths(array $paths): void
    {
        foreach ($paths as $path) {
            FileStorage::delete($path);
        }
    }
}
