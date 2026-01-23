<?php

namespace MetaFramework\Mediaclass\Traits;

use MetaFramework\Mediaclass\Config;

trait Accessors
{

    /**
     * Returns the bound model instance.
     */
    public function boundModel(): object
    {
        // For ghost models (no model_id), try to get the model relation
        if ($this->model_id === null) {
            // If relation is loaded, return it
            if ($this->relationLoaded('model') && $this->model) {
                return $this->model;
            }

            // Otherwise, create a new instance of the model type
            $modelClass = $this->model_type;

            // Handle morph map
            $morphMap = \Illuminate\Database\Eloquent\Relations\Relation::morphMap();
            if (!empty($morphMap)) {
                $modelClass = $morphMap[$modelClass] ?? $modelClass;
            }

            if (class_exists($modelClass)) {
                $instance = new $modelClass();
                $this->setRelation('model', $instance);
                return $instance;
            }
        }

        // For regular models, ensure the relation is loaded
        if (!$this->relationLoaded('model') || !$this->model) {
            $this->load('model');
        }

        // Check if model relation exists and has the model() method
        if ($this->model && method_exists($this->model, 'model')) {
            return $this->model->model()->instance;
        }

        // If model is already the instance (not a relationship), return it directly
        if ($this->model) {
            return $this->model;
        }

        // This should never happen, but throw a meaningful exception
        throw new \RuntimeException('Unable to resolve model for Media ID: ' . $this->id);
    }

    public function settings(): array
    {
        $boundModel = $this->boundModel();

        if (method_exists($boundModel, 'mediaclassSettings')) {
            $boundModelSettings = $boundModel->mediaclassSettings();
            if ($boundModelSettings && array_key_exists($this->group, $boundModelSettings)) {
                return $boundModelSettings[$this->group];
            }
        }

        $fillables = $boundModel->fillables;
        if (!is_array($fillables)) {
            return [];
        }

        $mediaFillables = array_filter($fillables, fn($key) => $key === 'media', ARRAY_FILTER_USE_KEY);
        $matchingGroup = array_filter($mediaFillables, fn($item) => ($item['group'] ?? Config::defaultGroup()) === $this->group);

        return (array)current($matchingGroup);
    }

}
