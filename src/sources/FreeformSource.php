<?php
/**
 * Webhook Notifier plugin for Craft CMS 5.x
 *
 * @link      https://coysh.digital
 * @copyright Copyright (c) Coysh Digital
 */

namespace coyshdigital\webhooknotifier\sources;

use Craft;
use Solspace\Freeform\Events\Submissions\SubmitEvent;
use Solspace\Freeform\Fields\Interfaces\NoStorageInterface;
use Solspace\Freeform\Form\Form;
use Solspace\Freeform\Services\SubmissionsService;
use Throwable;
use yii\base\Event;

/**
 * Notification source: a Freeform form was submitted.
 *
 * Only available when the Freeform plugin is installed and enabled; all
 * references to Freeform classes are reached only after that check, so the
 * plugin loads cleanly without Freeform present.
 *
 * @author Coysh Digital
 * @since 1.0.0
 */
class FreeformSource extends BaseSource
{
    // Static Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function id(): string
    {
        return 'freeform';
    }

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('webhook-notifier', 'Freeform submission');
    }

    /**
     * @inheritdoc
     */
    public static function isAvailable(): bool
    {
        return Craft::$app->getPlugins()->isPluginEnabled('freeform');
    }

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function description(): string
    {
        return Craft::t('webhook-notifier', 'Fires when a Freeform form is submitted. Filter by “formHandle” or “formId” to target a specific form. In the card, reference a single field with {fields.yourFieldHandle} (e.g. {fields.email}), or drop in every submitted field at once with {allFields}.');
    }

    /**
     * @inheritdoc
     */
    public function contextSchema(): array
    {
        return [
            'formHandle' => Craft::t('webhook-notifier', 'Form handle'),
            'formName' => Craft::t('webhook-notifier', 'Form name'),
            'formId' => Craft::t('webhook-notifier', 'Form ID'),
            'submissionId' => Craft::t('webhook-notifier', 'Submission ID'),
        ];
    }

    /**
     * @inheritdoc
     */
    public function cardVariables(): array
    {
        return array_merge($this->contextSchema(), [
            'fields.<handle>' => Craft::t('webhook-notifier', 'A submitted field value, by its handle'),
            'allFields' => Craft::t('webhook-notifier', 'All submitted fields, formatted'),
        ]);
    }

    /**
     * @inheritdoc
     */
    public function attachListeners(): void
    {
        Event::on(
            SubmissionsService::class,
            SubmissionsService::EVENT_AFTER_SUBMIT,
            function(SubmitEvent $event) {
                $this->dispatch($this->_buildContext($event->getForm(), $event->getSubmission()));
            }
        );
    }

    // Private Methods
    // =========================================================================

    /**
     * Builds the normalized context for a submission.
     *
     * @param Form $form
     * @param mixed $submission
     * @return array<string, mixed>
     */
    private function _buildContext(Form $form, mixed $submission): array
    {
        $fields = [];
        $fieldList = [];
        $lines = [];

        // Use the layout's fields (every page), not getFields() which only returns
        // the current/last page. Skip HTML blocks, submit buttons, etc. (anything
        // that doesn't store a value).
        foreach ($form->getLayout()->getFields() as $field) {
            if ($field instanceof NoStorageInterface) {
                continue;
            }

            $handle = $field->getHandle();
            if ($handle === null || $handle === '') {
                continue;
            }

            try {
                $value = trim($field->getValueAsString());
            } catch (Throwable) {
                continue;
            }

            $label = $field->getLabel();
            $fields[$handle] = $value;
            $fieldList[] = ['label' => $label, 'handle' => $handle, 'value' => $value];

            if ($value !== '') {
                $lines[] = "**{$label}:** {$value}";
            }
        }

        return [
            'form' => $form,
            'submission' => $submission,
            'formHandle' => $form->getHandle(),
            'formName' => $form->getName(),
            'formId' => $form->getId(),
            'submissionId' => $submission->id,
            'fields' => $fields,
            'fieldList' => $fieldList,
            'allFields' => implode("\n\n", $lines),
            'summary' => Craft::t('webhook-notifier', '“{form}” submitted', ['form' => $form->getName()]),
        ];
    }
}
