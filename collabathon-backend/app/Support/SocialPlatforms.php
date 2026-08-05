<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * The social platforms collected on both a developer and a channel partner's profile.
 * One list, so the create/edit form fields, the validation rules, and the "which
 * links does this profile actually have" display logic can't drift apart into three
 * different sets of platforms.
 */
class SocialPlatforms
{
    public const ALL = [
        'instagram' => 'Instagram',
        'facebook' => 'Facebook',
        'youtube' => 'YouTube',
        'twitter' => 'Twitter / X',
        'linkedin' => 'LinkedIn',
    ];

    /**
     * Validation rules for the five columns, keyed the way $request->validate() expects.
     * $sometimes matches an update() endpoint where every field is optional-if-absent
     * rather than a store() endpoint where "absent" just means "leave it null".
     */
    public static function rules(bool $sometimes = false): array
    {
        $rules = [];
        foreach (array_keys(self::ALL) as $key) {
            $rules[$key] = $sometimes
                ? ['sometimes', 'nullable', 'string', 'max:255']
                : ['nullable', 'string', 'max:255'];
        }

        return $rules;
    }

    /**
     * Only the platforms this model actually filled in — what a profile/show page
     * renders, so a blank field never shows up as an empty link row.
     *
     * @return array<int,array{key:string,label:string,value:string}>
     */
    public static function linksFor(Model $model): array
    {
        $links = [];

        foreach (self::ALL as $key => $label) {
            $value = $model->{$key} ?? null;
            if (! empty($value)) {
                $links[] = ['key' => $key, 'label' => $label, 'value' => $value];
            }
        }

        return $links;
    }
}
