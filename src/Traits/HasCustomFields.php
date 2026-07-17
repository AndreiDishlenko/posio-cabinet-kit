<?php

namespace Posio\AdminKit\Traits;

use stdClass;

/**
 * JSON-bag trait for a `settings` column. Always reads/writes an object
 * ('{}' never '[]') so `$model->settings->foo` never throws on an empty bag.
 *
 * The accessor/mutator names are fixed to `settings` — a host renaming the
 * underlying column (config('admin-kit.user_settings_column')) only changes
 * what the bundled migration creates; it must also add its own
 * getSettingsAttribute/setSettingsAttribute proxy if the real column is
 * named differently.
 */
trait HasCustomFields
{
    public function initializeHasCustomFields(): void
    {
        if (! isset($this->casts['settings'])) {
            $this->casts['settings'] = 'object';
        }

        if (! isset($this->attributes['settings'])) {
            $this->attributes['settings'] = '{}';
        }
    }

    public function getSettingsAttribute($value)
    {
        if ($value === null || $value === '') {
            return new stdClass();
        }

        $decoded = json_decode($value);

        return is_object($decoded) ? $decoded : new stdClass();
    }

    public function setSettingsAttribute($value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['settings'] = '{}';
            return;
        }

        $this->attributes['settings'] = is_string($value) ? $value : json_encode($value);
    }

    public function getCustomField(string $field)
    {
        return $this->settings->{$field} ?? null;
    }

    public function setCustomField(string $field, mixed $value): void
    {
        $settings = (object) ($this->settings ?? new stdClass());
        $settings->{$field} = $value;

        $this->settings = $settings;
        $this->save();
    }

    public function removeCustomField(string $field): void
    {
        $settings = (object) ($this->settings ?? new stdClass());
        unset($settings->{$field});

        $this->settings = $settings;
        $this->save();
    }
}
