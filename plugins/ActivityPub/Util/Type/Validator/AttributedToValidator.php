<?php

/*
 * This file is part of the ActivityPhp package.
 *
 * Copyright (c) landrok at github.com/landrok
 *
 * For the full copyright and license information, please see
 * <https://github.com/landrok/activitypub/blob/master/LICENSE>.
 */

namespace Plugin\ActivityPub\Util\Type\Validator;

use Exception;
use Plugin\ActivityPub\Util\Type\ValidatorTools;

/**
 * \Plugin\ActivityPub\Util\Type\Validator\AttributedToValidator is a dedicated
 * validator for attributedTo attribute.
 */
class AttributedToValidator extends ValidatorTools
{
    /**
     * Validate an attributedTo value
     *
     * @param mixed $value
     * @param mixed $container An Object type
     *
     * @throws Exception
     *
     * @return bool
     */
    public function validate(mixed $value, mixed $container): bool
    {
        return $this->validateListOrObject(
            $value,
            $container,
            $this->getCollectionActorsValidator()
        );
    }
}