<?php

namespace HandoverQueue;

/**
 * Class Item
 */
class Item
{
    /**
     * @var string UUID V4
     */
    private $id;

    /**
     * @var string Order number
     */
    private $caption;

    /**
     * @var string new|assemblyInProgress|readyForPickup|handedOver
     */
    private $status;

    /**
     * @var \DateTimeInterface
     */
    private $updated;

    /**
     * @return bool
     */
    public function isVisible()
    {
        if (!$this->isInStatus('handedOver')) {
            return false;
        }
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getCaption()
    {
        return $this->caption;
    }

    /**
     * @return string
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @return \DateTimeInterface
     */
    public function getUpdated()
    {
        return $this->updated;
    }

    /**
     * @param string $status
     *
     * @return bool
     */
    private function isInStatus(string $status):bool
    {
        return $this->getStatus() === $status;
    }
}
