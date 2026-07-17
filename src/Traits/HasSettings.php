<?php

namespace Posio\AdminKit\Traits;

/**
 * Thin, friendlier vocabulary over HasCustomFields (getSetting/setSetting
 * instead of getCustomField/setCustomField) — attach to any model that also
 * uses HasCustomFields, e.g. the host's User model.
 */
trait HasSettings
{
    public function getSetting(string $key)
    {
        return $this->getCustomField($key);
    }

    public function getSettings()
    {
        return $this->settings;
    }

    public function setSetting(string $key, mixed $value): bool
    {
        $this->setCustomField($key, $value);

        return true;
    }

    public function removeSetting(string $key): bool
    {
        $this->removeCustomField($key);

        return true;
    }
}
