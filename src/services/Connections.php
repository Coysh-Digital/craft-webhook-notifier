<?php
/**
 * Webhook Notifier plugin for Craft CMS 5.x
 *
 * @link      https://coysh.digital
 * @copyright Copyright (c) Coysh Digital
 */

namespace coyshdigital\webhooknotifier\services;

use Craft;
use coyshdigital\webhooknotifier\records\ConnectionRecord;
use craft\helpers\App;
use yii\base\Component;

/**
 * Connections service - manages Teams channel destinations and resolves their
 * (secret) webhook URLs at send time.
 *
 * A connection's URL is stored either as an environment-variable reference
 * (`$TEAMS_WEBHOOK_OPS`) or as a value encrypted with the application security
 * key - never in plain text.
 *
 * @author Coysh Digital
 * @since 1.0.0
 */
class Connections extends Component
{
    // Public Methods
    // =========================================================================

    /**
     * Returns all connections, ordered by name.
     *
     * @return ConnectionRecord[]
     */
    public function getAllConnections(): array
    {
        return ConnectionRecord::find()
            ->orderBy(['name' => SORT_ASC])
            ->all();
    }

    /**
     * Returns a connection by its ID.
     *
     * @param int $id
     * @return ConnectionRecord|null
     */
    public function getConnectionById(int $id): ?ConnectionRecord
    {
        return ConnectionRecord::findOne($id);
    }

    /**
     * Validates and saves a connection.
     *
     * @param ConnectionRecord $connection
     * @return bool
     */
    public function saveConnection(ConnectionRecord $connection): bool
    {
        return $connection->save();
    }

    /**
     * Deletes a connection by its ID.
     *
     * @param int $id
     * @return bool
     */
    public function deleteConnectionById(int $id): bool
    {
        $connection = $this->getConnectionById($id);

        if ($connection === null) {
            return false;
        }

        return (bool)$connection->delete();
    }

    /**
     * Applies a raw URL input to a connection, detecting environment-variable
     * references and encrypting direct values.
     *
     * @param ConnectionRecord $connection
     * @param string $input
     * @return void
     */
    public function applyUrlInput(ConnectionRecord $connection, string $input): void
    {
        $input = trim($input);

        if ($this->_looksLikeEnvVar($input)) {
            $connection->url = $input;
            $connection->isEnvVar = true;
            return;
        }

        // encryptByKey() returns raw binary; base64-encode it so it's safe to
        // store in a (utf8mb4) text column.
        $connection->url = base64_encode(Craft::$app->getSecurity()->encryptByKey($input));
        $connection->isEnvVar = false;
    }

    /**
     * Resolves a connection's stored URL to the actual webhook URL to POST to.
     *
     * @param ConnectionRecord $connection
     * @return string
     */
    public function resolveUrl(ConnectionRecord $connection): string
    {
        if ($connection->isEnvVar) {
            return (string)App::parseEnv($connection->url);
        }

        $ciphertext = base64_decode($connection->url, true);

        if ($ciphertext === false) {
            return '';
        }

        return (string)Craft::$app->getSecurity()->decryptByKey($ciphertext);
    }

    /**
     * Returns a display-safe representation of a connection's URL.
     *
     * @param ConnectionRecord $connection
     * @return string
     */
    public function maskUrl(ConnectionRecord $connection): string
    {
        if ($connection->isEnvVar) {
            return $connection->url;
        }

        $url = $this->resolveUrl($connection);

        if ($url === '') {
            return '';
        }

        // Show just enough to recognise it without leaking the secret.
        $prefix = mb_substr($url, 0, 28);

        return $prefix . '…';
    }

    // Private Methods
    // =========================================================================

    /**
     * Returns whether a value is an environment-variable reference (`$VAR`).
     *
     * @param string $value
     * @return bool
     */
    private function _looksLikeEnvVar(string $value): bool
    {
        return (bool)preg_match('/^\$[A-Za-z_][A-Za-z0-9_]*$/', $value);
    }
}
