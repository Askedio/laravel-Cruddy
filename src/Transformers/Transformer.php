<?php

namespace Askedio\Laravel5ApiController\Transformers;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Class Transformer
 *
 * Assists in transforming models or custom datasets
 *
 * @package KamranAhmed\Laraformer
 */
class Transformer
{
    public function __construct()
    {
    }

    /**
     * Transforms the classes having transform method
     *
     * @param $content
     * @return array
     */
    public function modal($name, $content)
    {
        if (is_object($content) && $this->isTransformable($content)) {
          $id = $content->getId();

          $data = $content->transform($content);
          $content = [
            'data' => [
                'type'       => strtolower($name),
                'id'         => $content->$$id,
                'attributes' => $content->transform($content)
            ]
          ];


        } elseif ($content instanceof LengthAwarePaginator) {

          $content = [
            'data' => [
                'type'       => strtolower($name),
                'attributes' => $this->transformObjects($content->items()),
                'pagination' => $this->getPaginationMeta($content)
            ]
          ];
        }


        return array_merge($content, [
          'jsonapi' => ['version' => '1.0']
        ]);
    }

    /**
     * Transforms an array of objects using the objects transform method
     *
     * @param $toTransform
     * @return array
     */
    private function transformObjects($toTransform)
    {
        $transformed = [];
        foreach ($toTransform as $key => $item) {
            $transformed[$key] = $this->isTransformable($item) ? $item->transform($item) : $item;
        }

        return $transformed;
    }

    /**
     * Checks whether the object is transformable or not
     *
     * @param $item
     * @return bool
     */
    private function isTransformable($item)
    {
        return is_object($item) && method_exists($item, 'transform');
    }

    /**
     * Gets the pagination meta data. Assumes that a paginator
     * instance is passed \Illuminate\Pagination\LengthAwarePaginator
     *
     * @param $paginator
     * @return array
     */
    private function getPaginationMeta($paginator)
    {
        return [
            'total'          => $paginator->total(),
            'per_page'       => $paginator->perPage(),
            'current_page'   => $paginator->currentPage(),
            'last_page'      => $paginator->lastPage(),
            'next_page_url'  => $paginator->nextPageUrl(),
            'prev_page_url'  => $paginator->previousPageUrl(),
            'has_pages'      => $paginator->hasPages(),
            'has_more_pages' => $paginator->hasMorePages(),
        ];
    }

    /**
     * Calls the transformation callback on each item of the dataset
     *
     * @param $content
     * @param $callback
     * @return array
     */
    private function callbackTransform($content, $callback)
    {
        // If it is not a dataset and just a
        // single array. Need to improve
        if (empty($content[0])) {
            $content = [$content];
        }

        $transformedData = [];
        foreach ($content as $key => $item) {
            $transformedData[$key] = $callback($item);
        }

        return $transformedData;
    }
}
