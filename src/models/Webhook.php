<?php

namespace pickhero\commerce\models;

use craft\base\Model;
use DateTime;

/**
 * Webhook configuration model
 */
class Webhook extends Model
{
    /** @var int|null */
    public ?int $id = null;

    /** @var string */
    public string $type = '';

    /** @var int|null PickHero webhook ID */
    public ?int $pickheroWebhookId = null;

    /** @var string|null Webhook secret for signature verification */
    public ?string $secret = null;

    public ?DateTime $dateCreated = null;
    public ?DateTime $dateUpdated = null;
    public ?string $uid = null;
    
    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['type', 'pickheroWebhookId'], 'required'],
        ];
    }
}
