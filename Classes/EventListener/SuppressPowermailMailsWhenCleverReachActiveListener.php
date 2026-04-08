<?php

declare(strict_types=1);

namespace Gedankenfolger\GedankenfolgerPowermailRedirect\EventListener;

use In2code\Powermail\Events\SendMailServicePrepareAndSendEvent;
use TYPO3\CMS\Core\Service\FlexFormService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Suppresses Powermail's built-in e-mail sending when CleverReach integration
 * is active for the current content element.
 *
 * When CleverReach is enabled the DOI (Double Opt-In) e-mail is handled
 * entirely by CleverReach, so Powermail's own sender/receiver mails must not
 * be sent to avoid duplicate or conflicting messages.
 *
 * The check is done by reading the FlexForm of the currently rendering content
 * element from the PSR-7 request attribute "currentContentObject" — the same
 * attribute Powermail's own FormController uses.
 */
final class SuppressPowermailMailsWhenCleverReachActiveListener
{
    public function __invoke(SendMailServicePrepareAndSendEvent $event): void
    {
        if (!$this->isCleverReachEnabled()) {
            return;
        }

        $event->setAllowedToSend(false);
    }

    private function isCleverReachEnabled(): bool
    {
        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;
        if ($request === null) {
            return false;
        }

        $cObj = $request->getAttribute('currentContentObject');
        if ($cObj === null) {
            return false;
        }

        $rawFlexForm = $cObj->data['tx_gedankenfolger_powermailredirect_flexform'] ?? '';
        if (!is_string($rawFlexForm) || $rawFlexForm === '') {
            return false;
        }

        /** @var FlexFormService $flexFormService */
        $flexFormService = GeneralUtility::makeInstance(FlexFormService::class);
        $flexFormData = $flexFormService->convertFlexFormContentToArray($rawFlexForm);

        return (int)($flexFormData['cleverreachEnabled'] ?? 0) === 1;
    }
}
