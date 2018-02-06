<?php

namespace HandoverQueue;

/**
 * Class Queue
 */
class Queue
{
    /**
     * @var Item[]
     */
    private $items = [];

    /**
     * @return Item[]
     */
    public function getVisibleItems()
    {
        return $this->items;
    }
}
