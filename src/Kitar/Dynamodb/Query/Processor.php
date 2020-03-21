<?php

namespace Kitar\Dynamodb\Query;

use Aws\Result;
use Aws\DynamoDb\Marshaler;
use Illuminate\Database\Query\Processors\Processor as BaseProcessor;

class Processor extends BaseProcessor
{
    public $marshaler;

    public function __construct()
    {
        $this->marshaler = new Marshaler;
    }

    protected function unmarshal(Result $res)
    {
        $responseArray = $res->toArray();

        if (! empty($responseArray['Item'])) {
            $responseArray['Item'] = $this->marshaler->unmarshalItem($responseArray['Item']);
        }

        if (! empty($responseArray['Items'])) {
            foreach ($responseArray['Items'] as &$item) {
                $item = $this->marshaler->unmarshalItem($item);
            }
        }

        return $responseArray;
    }

    public function processSingleItem(Result $awsResponse, $modelClass = null)
    {
        $response = $this->unmarshal($awsResponse);

        if (empty($modelClass)) {
            return $response;
        }

        if (! empty($response['Item'])) {
            $item = (new $modelClass)->newFromBuilder($response['Item']);
            unset($response['Item']);
            $item->setMeta($response ?? null);
            return $item;
        }
    }

    public function processMultipleItems(Result $awsResponse, $modelClass = null)
    {
        $response = $this->unmarshal($awsResponse);

        if (empty($modelClass)) {
            return $response;
        }

        $items = collect();

        foreach ($response['Items'] as $item) {
            $item = (new $modelClass)->newFromBuilder($item);
            $items->push($item);
        }

        unset($response['Items']);

        return $items->map(function ($item) use ($response) {
            $item->setMeta($response);
            return $item;
        });
    }
}
