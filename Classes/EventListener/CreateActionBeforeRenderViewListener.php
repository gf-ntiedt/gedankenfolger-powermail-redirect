<?php

declare(strict_types=1);

namespace Gedankenfolger\GedankenfolgerPowermailRedirect\EventListener;

use In2code\Powermail\Domain\Model\Mail;
use In2code\Powermail\Events\FormControllerCreateActionBeforeRenderViewEvent;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Http\PropagateResponseException;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Service\FlexFormService;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Intercepts Powermail form submissions at the earliest possible point.
 *
 * Listens to FormControllerCreateActionBeforeRenderViewEvent, which fires at the
 * very beginning of FormController::createAction() — before any system finisher
 * (database persistence, sender/receiver mail, opt-in email, confirmation page
 * rendering) is executed.
 *
 * If a redirect target is configured on the current Powermail content element,
 * a PropagateResponseException is thrown immediately. This stops the entire
 * Powermail processing pipeline: no data is saved, no emails are sent, and no
 * opt-in or confirmation page is triggered.
 *
 * If no redirect target is configured, the listener returns without action and
 * Powermail continues its normal processing flow.
 */
final class CreateActionBeforeRenderViewListener
{
    /**
     * Checks for a configured redirect target and redirects immediately if found.
     *
     * @throws PropagateResponseException When a valid redirect target is configured
     */
    public function __invoke(FormControllerCreateActionBeforeRenderViewEvent $event): void
    {
        $site = ($GLOBALS['TYPO3_REQUEST'] ?? null)?->getAttribute('site');
        $debugEnabled = $site instanceof Site
            && (bool)$site->getSettings()->get('powermailRedirect.debugLog', false);
        $log = static function (string $msg) use ($debugEnabled): void {
            if (!$debugEnabled) {
                return;
            }
            file_put_contents(
                Environment::getVarPath() . '/log/powermail_redirect_debug.log',
                date('H:i:s') . ' [EventListener] ' . $msg . "\n",
                FILE_APPEND
            );
        };

        $log('CreateActionBeforeRenderViewListener fired');

        // Use the plugin subrequest from the FormController — only this request has
        // 'currentContentObject' set. $GLOBALS['TYPO3_REQUEST'] is the global frontend
        // request and does NOT carry the ContentObjectRenderer attribute.
        $request = $event->getFormController()->getRequest();
        if ($request === null) {
            $log('RETURN: no plugin request');
            return;
        }

        $contentObject = $request->getAttribute('currentContentObject');
        if ($contentObject === null) {
            $log('RETURN: no contentObject');
            return;
        }

        $rawFlexForm = $contentObject->data['tx_gedankenfolger_powermailredirect_flexform'] ?? '';
        if (!is_string($rawFlexForm) || $rawFlexForm === '') {
            $log('RETURN: no FlexForm data');
            return;
        }

        $flexFormData = GeneralUtility::makeInstance(FlexFormService::class)
            ->convertFlexFormContentToArray($rawFlexForm);
        $log('flexFormData: ' . json_encode($flexFormData));

        $redirectTarget = trim((string)($flexFormData['redirectTarget'] ?? ''));
        $log('redirectTarget: ' . $redirectTarget);
        if ($redirectTarget === '') {
            $log('RETURN: redirectTarget is empty');
            return;
        }

        $additionalParams = $this->buildAdditionalParams($event->getMail(), $flexFormData, $log);
        $log('additionalParams: ' . $additionalParams);

        // Use the already-initialised ContentObjectRenderer that carries the full frontend
        // request context. A fresh makeInstance() would be uninitialised and cause
        // typoLink_URL() to return an empty string.
        $targetUrl = $contentObject->typoLink_URL([
            'parameter'        => $redirectTarget,
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
        throw new PropagateResponseException(new RedirectResponse($targetUrl, 302), 1);
    }

    /**
     * Builds URL query parameters from the selected Powermail field UIDs.
     *
     * Resolves field UIDs to their markers via Mail::getAnswersByFieldUid().
     * Parameters are placed in the tx_powermail_pi1[field] namespace to allow
     * native Powermail prefill on the target page via TypoScript.
     *
     * Security: only markers matching [a-zA-Z0-9_] are accepted to
     * prevent parameter injection. Empty answer values are skipped.
     *
     * @param array<string, mixed> $flexFormData Parsed FlexForm data
     * @param callable             $log          Debug log closure
     * @return string Additional URL-encoded query string,
     *                e.g. "&tx_powermail_pi1[field][e_mail]=foo%40bar.de"
     */
    private function buildAdditionalParams(Mail $mail, array $flexFormData, callable $log): string
    {
        $fieldUids = GeneralUtility::intExplode(
            ',',
            (string)($flexFormData['transferFields'] ?? ''),
            true
        );

        if (empty($fieldUids)) {
            return '';
        }

        $answersByUid = $mail->getAnswersByFieldUid();
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

        $log('buildAdditionalParams result: ' . $additionalParams);

        return $additionalParams;
    }
}
