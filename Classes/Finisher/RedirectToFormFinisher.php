<?php

declare(strict_types=1);

namespace Gedankenfolger\GedankenfolgerPowermailRedirect\Finisher;

use In2code\Powermail\Finisher\AbstractFinisher;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Http\PropagateResponseException;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Service\FlexFormService;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Powermail finisher that redirects to a configured target page after form submission.
 *
 * Reads the redirect target page and fields to transfer from the FlexForm
 * configured directly on the Powermail content element
 * (field: tx_gedankenfolger_powermailredirect_flexform).
 *
 * Selected field values are passed as GET parameters in the
 * tx_powermail_pi1[field] namespace so Powermail can prefill them
 * on the target page natively.
 *
 * TypoScript registration (no config needed per form):
 *
 *   plugin.tx_powermail.settings.setup.finishers.200 {
 *       class = Gedankenfolger\GedankenfolgerPowermailRedirect\Finisher\RedirectToFormFinisher
 *   }
 */
class RedirectToFormFinisher extends AbstractFinisher
{
    /**
     * Redirects to the configured target page after form submission,
     * passing selected field values as prefill GET parameters.
     *
     * Exits early if:
     * - No FlexForm data is present on the content element
     * - No redirect target page UID is configured
     * - The generated URL does not belong to the current site (security check)
     *
     * Note: confirmationAction never invokes finishers, so no guard is needed.
     * Note: Double Opt-In is intentionally not blocked – the redirect fires
     * immediately after the initial createAction so the user can be forwarded
     * to a pre-filled follow-up form while the opt-in e-mail runs in parallel.
     *
     * @throws PropagateResponseException When the redirect is triggered
     */
    public function redirectToFormFinisher(): void
    {
        $site = ($GLOBALS['TYPO3_REQUEST'] ?? null)?->getAttribute('site');
        $debugEnabled = $site instanceof Site
            && (bool)$site->getSettings()->get('powermailRedirect.debugLog', false);
        $log = static function (string $msg) use ($debugEnabled): void {
            if (!$debugEnabled) {
                return;
            }
            file_put_contents(Environment::getVarPath() . '/log/powermail_redirect_debug.log', date('H:i:s') . ' ' . $msg . "\n", FILE_APPEND);
        };

        $log('redirectToFormFinisher() called');

        $flexFormData = $this->getFlexFormData();
        $log('flexFormData: ' . json_encode($flexFormData));
        if (empty($flexFormData)) {
            $log('RETURN: flexFormData empty');
            return;
        }

        // Exit early if no target page is configured
        $targetPageUid = (int)($flexFormData['redirectPageUid'] ?? 0);
        $log('targetPageUid: ' . $targetPageUid);
        if ($targetPageUid === 0) {
            $log('RETURN: targetPageUid is 0');
            return;
        }

        $additionalParams = $this->buildAdditionalParams($flexFormData);
        $log('additionalParams: ' . $additionalParams);

        // Use the already-initialised ContentObjectRenderer from Powermail's controller.
        // A freshly created instance (makeInstance) has no frontend request context
        // and would cause typoLink_URL() to return an empty string.
        $targetUrl = $this->contentObject->typoLink_URL([
            'parameter'        => $targetPageUid,
            'additionalParams' => $additionalParams,
            'forceAbsoluteUrl' => true,
        ]);
        $log('targetUrl: ' . $targetUrl);

        // Security: only allow redirects within the current site
        $baseUrl = (string)GeneralUtility::getIndpEnv('TYPO3_SITE_URL');
        $log('baseUrl: ' . $baseUrl);
        if (!str_starts_with($targetUrl, $baseUrl)) {
            $log('RETURN: URL security check failed');
            return;
        }

        $log('REDIRECTING to: ' . $targetUrl);
        throw new PropagateResponseException(
            new RedirectResponse($targetUrl, 302),
            1
        );
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
     * Builds URL query parameters from the selected Powermail field UIDs.
     *
     * Resolves field UIDs to their markers via getAnswersByFieldUid().
     * Parameters are placed in the tx_powermail_pi1[field] namespace.
     *
     * Security: only markers matching [a-zA-Z0-9_] are accepted to
     * prevent parameter injection. Empty answer values are skipped.
     *
     * @param array<string, mixed> $flexFormData Parsed FlexForm data
     * @return string Additional URL-encoded query string,
     *                e.g. "&tx_powermail_pi1[field][e_mail]=foo%40bar.de"
     */
    private function buildAdditionalParams(array $flexFormData): string
    {
        $fieldUids = GeneralUtility::intExplode(
            ',',
            (string)($flexFormData['transferFields'] ?? ''),
            true
        );

        if (empty($fieldUids)) {
            return '';
        }

        $answersByUid  = $this->getMail()->getAnswersByFieldUid();
        $additionalParams = '';

        foreach ($fieldUids as $fieldUid) {
            $answer = $answersByUid[$fieldUid] ?? null;
            if ($answer === null) {
                continue;
            }

            $marker = $answer->getField()->getMarker();

            // Only allow safe marker names to prevent parameter injection
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $marker)) {
                continue;
            }

            $value = (string)$answer->getValue();
            if ($value === '') {
                continue;
            }

            $additionalParams .= '&tx_powermail_pi1[field]['
                . rawurlencode($marker) . ']='
                . rawurlencode($value);
        }

        return $additionalParams;
    }
}
