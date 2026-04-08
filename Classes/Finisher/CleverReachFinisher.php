<?php

declare(strict_types=1);

namespace Gedankenfolger\GedankenfolgerPowermailRedirect\Finisher;

use In2code\Powermail\Finisher\AbstractFinisher;
use TYPO3\CMS\Core\Service\FlexFormService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class CleverReachFinisher extends AbstractFinisher
{
    /**
     * Sends form data to a CleverReach HTML form URL via a background CURL POST,
     * triggering CleverReach's own Double Opt-In flow.
     *
     * Per-CE configuration (enabled, URL, e-mail marker) is read from the custom
     * FlexForm column tx_gedankenfolger_powermailredirect_flexform (sheet: sCleverReach).
     *
     * Additional field mappings (Powermail marker → CleverReach field name) are read
     * from TypoScript: plugin.tx_powermail.settings.setup.cleverreach.additionalFields
     *
     * Exits silently if CleverReach is not enabled, no URL is configured,
     * or no valid e-mail address is found. Errors are never surfaced to the user.
     */
    public function cleverReachFinisher(): void
    {
        $flexFormData = $this->getFlexFormData();
        if (empty($flexFormData)) {
            return;
        }

        $enabled = (int)($flexFormData['cleverreachEnabled'] ?? 0);
        if ($enabled !== 1) {
            return;
        }

        $targetUrl = trim((string)($flexFormData['cleverreachTargetUrl'] ?? ''));
        if ($targetUrl === '') {
            return;
        }

        $answers = $this->getMail()->getAnswersByFieldMarker();
        $additionalFields = $this->settings['cleverreach']['additionalFields'] ?? [];
        $postData = [];

        if (is_array($additionalFields) && !empty($additionalFields)) {
            // Explicit mapping configured in TypoScript:
            // plugin.tx_powermail.settings.setup.cleverreach.additionalFields
            // Format: Powermail marker = CleverReach field name (may be a numeric ID)
            foreach ($additionalFields as $powermailMarker => $cleverreachField) {
                if (!isset($answers[$powermailMarker])) {
                    continue;
                }
                $value = trim((string)$answers[$powermailMarker]->getValue());
                if ($value !== '') {
                    $postData[(string)$cleverreachField] = $value;
                }
            }
        } else {
            // No mapping configured: use Powermail markers directly as POST field names.
            // Works for CleverReach forms where field names match Powermail markers (e.g. "email").
            foreach ($answers as $marker => $answer) {
                $value = trim((string)$answer->getValue());
                if ($value !== '') {
                    $postData[(string)$marker] = $value;
                }
            }
        }

        // A valid e-mail address is required for CleverReach to accept the submission
        $email = $postData['email'] ?? '';
        if ($email === '' || !GeneralUtility::validEmail($email)) {
            return;
        }

        $this->sendPost($targetUrl, $postData);
    }

    /**
     * Reads and parses the FlexForm data from the current content element.
     *
     * @return array<string, mixed> Parsed FlexForm data, or empty array if unavailable
     */
    private function getFlexFormData(): array
    {
        $rawFlexForm = $this->contentObject->data['tx_gedankenfolger_powermailredirect_flexform'] ?? '';
        if (!is_string($rawFlexForm) || $rawFlexForm === '') {
            return [];
        }

        /** @var FlexFormService $flexFormService */
        $flexFormService = GeneralUtility::makeInstance(FlexFormService::class);

        return $flexFormService->convertFlexFormContentToArray($rawFlexForm);
    }

    /**
     * Sends a POST request to the given URL with the provided data.
     * Failures are silently ignored — the form submission must not be blocked.
     */
    private function sendPost(string $url, array $data): void
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        curl_exec($ch);
        curl_close($ch);
    }
}