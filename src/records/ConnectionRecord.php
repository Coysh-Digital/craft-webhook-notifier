<?php
/**
 * Webhook Notifier plugin for Craft CMS 5.x
 *
 * @link      https://coysh.digital
 * @copyright Copyright (c) Coysh Digital
 */

namespace coyshdigital\webhooknotifier\records;

use craft\db\ActiveRecord;

/**
 * Connection record — a named Teams channel destination.
 *
 * @property int $id
 * @property string $name
 * @property string $url The webhook URL: an `$ENV_VAR` reference or an encrypted value.
 * @property bool $isEnvVar Whether {@see $url} is an environment-variable reference.
 * @property bool $enabled
 *
 * @author Coysh Digital
 * @since 1.0.0
 */
class ConnectionRecord extends ActiveRecord
{
    // Static Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%webhooknotifier_connections}}';
    }
}
