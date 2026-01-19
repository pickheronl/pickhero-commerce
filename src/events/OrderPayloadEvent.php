<?php


namespace pickhero\commerce\events;

/**
 * Event triggered before order payload is sent to PickHero
 *
 * Allows modification of the complete order payload including rows,
 * customer data, and addresses before submission.
 */
class OrderPayloadEvent extends OrderEvent
{
    /**
     * The complete order payload to be sent to PickHero
     */
    public array $payload = [];
}
