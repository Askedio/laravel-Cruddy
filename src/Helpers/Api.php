<?php

namespace Askedio\Laravel5ApiController\Helpers;

class Api
{
    /** @var string */
    private $version;

    /**
     * Get API version.
     *
     * @return string
     */
    public function getVersion()
    {
        return $this->version ?: config('jsonapi.version');
    }

    /**
     * Set version.
     *
     * @param string $version
     *
     * @return void
     */
    public function setVersion($version)
    {
        $this->version = $version;
    }

    /**
     * List of included options from input.
     *
     * @return Illuminate\Http\Request
     */
    public function includes()
    {
        return request()->input('include') ? explode(',', request()->input('include')) : [];
    }
}
