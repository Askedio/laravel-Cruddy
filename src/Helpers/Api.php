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
        return collect(request()->input('include') ? explode(',', request()->input('include')) : []);
    }

    /**
     * List of fields from input.
     *
     * @return array
     */
    public function fields()
    {
        $results = [];
        foreach (array_filter(request()->input('fields', [])) as $type => $members) {
            foreach (explode(',', $members) as $member) {
                $results[$type][] = $member;
            }
        }

        return collect($results);
    }

    public function jsonBody()
    {
        return collect(request()->json()->all())->get('data');
    }
}
