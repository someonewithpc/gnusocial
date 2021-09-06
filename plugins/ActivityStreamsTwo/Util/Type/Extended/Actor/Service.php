<?php

/*
 * This file is part of the ActivityPhp package.
 *
 * Copyright (c) landrok at github.com/landrok
 *
 * For the full copyright and license information, please see
 * <https://github.com/landrok/activitypub/blob/master/LICENSE>.
 */

namespace Plugin\ActivityStreamsTwo\Util\Type\Extended\Actor;

use Plugin\ActivityStreamsTwo\Util\Type\Extended\AbstractActor;

/**
 * \Plugin\ActivityStreamsTwo\Util\Type\Extended\Actor\Service is an implementation of
 * one of the Activity Streams Extended Types.
 *
 * Represents a service of any kind.
 *
 * @see https://www.w3.org/TR/activitystreams-vocabulary/#dfn-service
 */
class Service extends AbstractActor
{
    /**
     * @var string
     */
    protected string $type = 'Service';
}