<?php

namespace App\Transformers;

use Dingo\Api\Http\Request;
use Dingo\Api\Transformer\Binding;
use Dingo\Api\Transformer\Adapter\Fractal;
use Illuminate\Contracts\Pagination\Paginator as IlluminatePaginator;

class Transformer extends Fractal
{
    public function transform($response, $transformer, Binding $binding, Request $request)
    {
        // Used for $_GET['include']
        // $this->parseFractalIncludes($request);

        $resource = $this->createResource($response, $transformer, $parameters = $binding->getParameters());

        // If the response is a paginator then we'll create a new paginator
        // adapter for Laravel and set the paginator instance on our
        // collection resource.
        if ($response instanceof IlluminatePaginator) {
            $paginator = $this->createPaginatorAdapter($response);

            $resource->setPaginator($paginator);
        }

        if ($this->shouldEagerLoad($response)) {
            $eagerLoads = $this->mergeEagerLoads($transformer, $this->fractal->getRequestedIncludes());

            $response->load($eagerLoads);
        }

        foreach ($binding->getMeta() as $key => $value) {
            $resource->setMetaValue($key, $value);
        }

        $binding->fireCallback($resource, $this->fractal);

        $identifier = isset($parameters['identifier']) ? $parameters['identifier'] : null;

        return $this->fractal->createData($resource, $identifier)->toArray();
    }
}
