<?php

namespace pickhero\commerce\events;

use craft\base\Event;
use craft\elements\Address;

/**
 * Event triggered when transforming an address to PickHero payload format
 * 
 * Allows modification of address data before sending to PickHero,
 * useful for adding custom address fields.
 */
class AddressEvent extends Event
{
    public const TYPE_DELIVERY = 'delivery';
    public const TYPE_INVOICE = 'invoice';
    public const TYPE_CUSTOMER = 'customer';

    /**
     * The Craft address being transformed
     */
    public ?Address $address = null;

    /**
     * The type of address (delivery, invoice, or customer)
     */
    public string $type = self::TYPE_DELIVERY;

    /**
     * The address payload that will be sent to PickHero
     * Modify this array to add or change fields
     */
    public array $payload = [];
}

