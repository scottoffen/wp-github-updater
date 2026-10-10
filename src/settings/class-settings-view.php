<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Internal\Settings;

use ScottOffen\WpGithubUpdater\Internal\Platform\SettingsText;
use ScottOffen\WpGithubUpdater\Internal\Release\Outcome;

/**
 * A view for rendering a `SettingsModel` as the HTML of the settings section.
 *
 * @internal
 */
final class SettingsView
{
    public function __construct(private readonly SettingsText $text)
    {
    }

    /**
     * Returns the HTML of the settings section.
     *
     * Every value and every text is escaped, except the `fields` value of a
     * form, which is placed in the form as it is.
     *
     * @return string A `div` element with the class `wp-github-updater`. It
     *     has an `h2` element for the heading when the heading isn't empty, the
     *     notice, and a table with a row for the token and a row for the
     *     status.
     */
    public function render(SettingsModel $model): string
    {
        $html = '<div class="wp-github-updater">';

        if ($model->heading !== '') {
            $html .= '<h2>' . $this->escape($model->heading) . '</h2>';
        }

        $html .= $this->notice($model->notice);
        $html .= '<table class="form-table" role="presentation"><tbody>';
        $html .= '<tr><th scope="row"><label for="' . $this->escape($model->tokenField) . '">' . $this->escape($this->text->tokenLabel()) . '</label></th>';
        $html .= '<td>' . $this->tokenCell($model) . '</td></tr>';
        $html .= '<tr><th scope="row">' . $this->escape($this->text->statusLabel()) . '</th>';
        $html .= '<td>' . $this->statusCell($model) . '</td></tr>';
        $html .= '</tbody></table></div>';

        return $html;
    }

    /**
     * Returns the element for a notice.
     *
     * @return string An empty string when `$code` isn't a notice code.
     *     Otherwise, a `div` element with the text for the code and the class
     *     `notice-success` for a success or `notice-error` for any other code.
     */
    private function notice(string $code): string
    {
        if (!Notice::isKnown($code)) {
            return '';
        }

        $class = Notice::isSuccess($code) ? 'notice-success' : 'notice-error';

        return '<div class="notice ' . $class . ' is-dismissible"><p>' . $this->escape($this->text->notice($code)) . '</p></div>';
    }

    /**
     * Returns the content of the token cell.
     *
     * @return string When the token constant is in use, a disabled read-only
     *     text input with the name of the constant, and the help text for the
     *     constant. Otherwise, a form with a password input and a save button,
     *     a form with a remove button when the masked token isn't empty, and
     *     the help text for the token. The masked token is the placeholder of
     *     the password input.
     */
    private function tokenCell(SettingsModel $model): string
    {
        $field = $this->escape($model->tokenField);
        $help = $field . '-help';

        if ($model->locked) {
            return '<input type="text" id="' . $field . '" class="regular-text" value="' . $this->escape($model->constantName) . '" disabled readonly aria-describedby="' . $help . '">'
                . '<p class="description" id="' . $help . '">' . $this->escape($this->text->constantHelp($model->constantName)) . '</p>';
        }

        $placeholder = $model->maskedToken !== '' ? ' placeholder="' . $this->escape($model->maskedToken) . '"' : '';

        $html = $this->openForm($model->saveForm)
            . '<input type="password" id="' . $field . '" name="' . $field . '" class="regular-text" autocomplete="new-password"' . $placeholder . ' aria-describedby="' . $help . '"> '
            . '<button type="submit" class="button button-primary">' . $this->escape($this->text->saveButton()) . '</button></form>';

        if ($model->maskedToken !== '') {
            $html .= $this->openForm($model->removeForm)
                . '<button type="submit" class="button">' . $this->escape($this->text->removeButton()) . '</button></form>';
        }

        return $html . '<p class="description" id="' . $help . '">' . $this->escape($this->text->tokenHelp()) . '</p>';
    }

    /**
     * Returns the content of the status cell.
     *
     * @return string A `p` element with the status sentences, followed by the
     *     form with the check button.
     */
    private function statusCell(SettingsModel $model): string
    {
        return '<p>' . $this->statusSentences($model) . '</p>'
            . $this->openForm($model->checkForm)
            . '<button type="submit" class="button">' . $this->escape($this->text->checkButton()) . '</button></form>';
    }

    /**
     * Returns the status sentences.
     *
     * @return string The sentences, joined with a space. The first is the text
     *     for no check yet, or the text for the time of the check. The second
     *     is the installed version, when there is one. When a check has run,
     *     the next is the version of the release, when there is one. After it
     *     is the text for no release found when the outcome is `NO_RELEASE` and
     *     there is no release, or otherwise the text for the problem in a
     *     `span` element with the class `wp-github-updater-problem` when the
     *     outcome isn't `OK`.
     */
    private function statusSentences(SettingsModel $model): string
    {
        $state = $model->state;
        $sentences = [];

        if (!$state->hasChecked()) {
            $sentences[] = $this->escape($this->text->neverChecked());
        } else {
            $sentences[] = $this->escape($this->text->lastChecked($model->whenChecked));
        }

        if ($model->installedVersion !== '') {
            $sentences[] = $this->escape($this->text->installed($model->installedVersion));
        }

        if ($state->hasChecked()) {
            if ($state->release !== null) {
                $sentences[] = $this->escape($this->text->latest($state->release->version));
            }

            if ($state->outcome === Outcome::NO_RELEASE && $state->release === null) {
                $sentences[] = $this->escape($this->text->noneFound());
            } elseif (!$state->succeeded()) {
                $sentences[] = '<span class="wp-github-updater-problem">' . $this->escape($this->text->problem($state->outcome)) . '</span>';
            }
        }

        return implode(' ', $sentences);
    }

    /**
     * Returns the start tag of a form followed by its hidden fields.
     */
    private function openForm(SettingsForm $form): string
    {
        return '<form method="post" action="' . $this->escape($form->url) . '">' . $form->fields;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
