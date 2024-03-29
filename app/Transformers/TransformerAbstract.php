<?php

namespace App\Transformers;

use League\Fractal\TransformerAbstract as BasicTransformer;

abstract class TransformerAbstract extends BasicTransformer {
    protected $fields = [];
    protected $model = [];

    protected function transformWithField($data)
    {
        if (isset($this->fields['only'])) {
            return $this->only($data);
        }
        return $this->include($this->filter($data));
    }

    protected function only($data)
    {
        $temp = $this->defaultIncludes;
        $this->defaultIncludes = [];
        if (is_string($this->fields['only'])) {
            $filter = $this->fields['only'];
            $index = collect($this->defaultIncludes)->search($filter);
            if ($index !== false) {
                unset($this->defaultIncludes[$index]);
            } else if (isset($data[$filter])) {
                unset($data[$filter]);
            }
        } else {
            foreach($this->fields['only'] as $filter) {
                $index = collect($temp)->search($filter);
                if ($index !== false) {
                    $this->defaultIncludes[] = $filter;
                }
            }
        }
        return array_intersect_key($data, array_flip($this->fields['only']));
    }

    protected function filter($data)
    {
        if (isset($this->fields['filter'])) {
            if (is_string($this->fields['filter'])) {
                $filter = $this->fields['filter'];
                $index = collect($this->defaultIncludes)->search($filter);
                if ($index !== false) {
                    unset($this->defaultIncludes[$index]);
                } else if (isset($data[$filter])) {
                    unset($data[$filter]);
                }
            } else {
                foreach($this->fields['filter'] as $filter) {
                    $index = collect($this->defaultIncludes)->search($filter);
                    if ($index !== false) {
                        unset($this->defaultIncludes[$index]);
                    } else if (isset($data[$filter])) {
                        unset($data[$filter]);
                    }
                }
            }
        }
        return $data;
    }

    protected function include($data)
    {
        if (isset($this->fields['include'])) {
            if (is_string($this->fields['include'])) {
                $include = $this->fields['include'];
                if (collect($this->availableIncludes)->contains($include)) {
                    $this->defaultIncludes[] = $include;
                    $data[$include] = $this->{"include$include"}($this->model);
                } else {
                    $data[$include] = $this->model->{$include};
                }
            } else {
                foreach($this->fields['include'] as $include) {
                    if (collect($this->availableIncludes)->contains($include)) {
                        $this->defaultIncludes[] = $include;
                        $data[$include] = $this->{"include$include"}($this->model);
                    } else {
                        $data[$include] = $this->model->{$include};
                    }
                }
            }
        }
        return $data;
    }
}
