<?php


namespace pickhero\commerce\events;

use craft\base\Event;
use craft\commerce\elements\Order;

class OrderEvent extends Event
{
    public ?Order $order = null;
}
